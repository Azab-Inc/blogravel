locals {
  name_prefix = "blogravel-${var.environment}"
  common_env = {
    APP_ENV                     = "production"
    APP_DEBUG                   = "false"
    APP_URL                     = "https://${var.domain}"
    TENANCY_PLATFORM_DOMAIN     = var.domain
    SESSION_DOMAIN              = ".${var.domain}"
    DB_CONNECTION               = "pgsql"
    DB_DATABASE                 = var.db_name
    DB_USERNAME                 = var.db_user
    DB_HOST                     = google_sql_database_instance.postgres.private_ip_address
    DB_PORT                     = "5432"
    QUEUE_CONNECTION            = "database"
    CACHE_STORE                 = "database"
    FILESYSTEM_DISK             = "s3"
    BACKUP_DISK                 = "s3"
    AWS_BUCKET                  = google_storage_bucket.media_backups.name
    AWS_DEFAULT_REGION          = var.region
    AWS_ENDPOINT                = "https://storage.googleapis.com"
    AWS_USE_PATH_STYLE_ENDPOINT = "false"
  }
}

resource "google_project_service" "services" {
  for_each = toset([
    "artifactregistry.googleapis.com",
    "cloudscheduler.googleapis.com",
    "run.googleapis.com",
    "sqladmin.googleapis.com",
    "secretmanager.googleapis.com",
    "servicenetworking.googleapis.com",
    "storage.googleapis.com",
  ])

  service            = each.value
  disable_on_destroy = false
}

resource "google_artifact_registry_repository" "app" {
  location      = var.region
  repository_id = var.artifact_repository
  format        = "DOCKER"
  description   = "Blogravel production images"
  depends_on    = [google_project_service.services]
}

resource "google_compute_network" "private" {
  name                    = "${local.name_prefix}-network"
  auto_create_subnetworks = false
}

resource "google_compute_subnetwork" "private" {
  name          = "${local.name_prefix}-subnet"
  ip_cidr_range = "10.10.0.0/24"
  region        = var.region
  network       = google_compute_network.private.id
}

resource "google_compute_global_address" "private_services" {
  name          = "${local.name_prefix}-private-services"
  purpose       = "VPC_PEERING"
  address_type  = "INTERNAL"
  prefix_length = 16
  network       = google_compute_network.private.id
}

resource "google_service_networking_connection" "private_services" {
  network                 = google_compute_network.private.id
  service                 = "servicenetworking.googleapis.com"
  reserved_peering_ranges = [google_compute_global_address.private_services.name]
}

resource "google_sql_database_instance" "postgres" {
  name             = "${local.name_prefix}-postgres"
  database_version = "POSTGRES_17"
  region           = var.region

  settings {
    tier              = "db-f1-micro"
    availability_type = "ZONAL"
    disk_type         = "PD_SSD"
    disk_size         = 10
    disk_autoresize   = true

    backup_configuration {
      enabled                        = true
      point_in_time_recovery_enabled = true
    }

    ip_configuration {
      ipv4_enabled    = false
      private_network = google_compute_network.private.id
    }
  }

  deletion_protection = true
  depends_on          = [google_service_networking_connection.private_services]
}

resource "google_sql_database" "app" {
  name     = var.db_name
  instance = google_sql_database_instance.postgres.name
}

resource "google_storage_bucket" "media_backups" {
  name                        = var.storage_bucket_name
  location                    = var.region
  uniform_bucket_level_access = true
  public_access_prevention    = "enforced"

  versioning {
    enabled = true
  }
}

resource "google_secret_manager_secret" "app_key" {
  secret_id = var.app_key_secret_id
  replication {
    auto {}
  }
}

resource "google_secret_manager_secret" "db_password" {
  secret_id = var.db_password_secret_id
  replication {
    auto {}
  }
}

resource "google_secret_manager_secret" "aws_access_key" {
  secret_id = var.aws_access_key_secret_id
  replication {
    auto {}
  }
}

resource "google_secret_manager_secret" "aws_secret_access_key" {
  secret_id = var.aws_secret_access_key_secret_id
  replication {
    auto {}
  }
}

resource "google_service_account" "runtime" {
  account_id   = "${replace(local.name_prefix, "_", "-")}-runtime"
  display_name = "Blogravel runtime"
}

resource "google_service_account" "scheduler" {
  account_id   = "${replace(local.name_prefix, "_", "-")}-scheduler"
  display_name = "Blogravel Cloud Scheduler invoker"
}

resource "google_storage_bucket_iam_member" "runtime_storage" {
  bucket = google_storage_bucket.media_backups.name
  role   = "roles/storage.objectAdmin"
  member = "serviceAccount:${google_service_account.runtime.email}"
}

resource "google_secret_manager_secret_iam_member" "runtime_app_key" {
  secret_id = google_secret_manager_secret.app_key.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.runtime.email}"
}

resource "google_secret_manager_secret_iam_member" "runtime_db_password" {
  secret_id = google_secret_manager_secret.db_password.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.runtime.email}"
}

resource "google_secret_manager_secret_iam_member" "runtime_aws_access_key" {
  secret_id = google_secret_manager_secret.aws_access_key.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.runtime.email}"
}

resource "google_secret_manager_secret_iam_member" "runtime_aws_secret_access_key" {
  secret_id = google_secret_manager_secret.aws_secret_access_key.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.runtime.email}"
}

resource "google_cloud_run_v2_service" "web" {
  name     = "${local.name_prefix}-web"
  location = var.region

  template {
    service_account = google_service_account.runtime.email
    vpc_access {
      network_interfaces {
        network    = google_compute_network.private.name
        subnetwork = google_compute_subnetwork.private.name
      }
    }
    scaling {
      min_instance_count = 0
      max_instance_count = 3
    }
    containers {
      image = var.image_digest
      ports {
        container_port = 8080
      }
      env {
        name  = "CONTAINER_ROLE"
        value = "web"
      }
      dynamic "env" {
        for_each = local.common_env
        content {
          name  = env.key
          value = env.value
        }
      }
      env {
        name = "APP_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.app_key.secret_id
            version = "latest"
          }
        }
      }
      env {
        name = "DB_PASSWORD"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.db_password.secret_id
            version = "latest"
          }
        }
      }
      env {
        name = "AWS_ACCESS_KEY_ID"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.aws_access_key.secret_id
            version = "latest"
          }
        }
      }
      env {
        name = "AWS_SECRET_ACCESS_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.aws_secret_access_key.secret_id
            version = "latest"
          }
        }
      }
    }
  }
}

resource "google_cloud_run_v2_service" "worker" {
  name     = "${local.name_prefix}-worker"
  location = var.region

  template {
    service_account = google_service_account.runtime.email
    vpc_access {
      network_interfaces {
        network    = google_compute_network.private.name
        subnetwork = google_compute_subnetwork.private.name
      }
    }
    scaling {
      min_instance_count = 1
      max_instance_count = 1
    }
    containers {
      image = var.image_digest
      ports {
        container_port = 8080
      }
      env {
        name  = "CONTAINER_ROLE"
        value = "worker"
      }
      dynamic "env" {
        for_each = local.common_env
        content {
          name  = env.key
          value = env.value
        }
      }
      env {
        name = "APP_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.app_key.secret_id
            version = "latest"
          }
        }
      }
      env {
        name = "DB_PASSWORD"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.db_password.secret_id
            version = "latest"
          }
        }
      }
      env {
        name = "AWS_ACCESS_KEY_ID"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.aws_access_key.secret_id
            version = "latest"
          }
        }
      }
      env {
        name = "AWS_SECRET_ACCESS_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.aws_secret_access_key.secret_id
            version = "latest"
          }
        }
      }
    }
  }
}

resource "google_cloud_run_v2_job" "scheduler" {
  name     = "${local.name_prefix}-scheduler"
  location = var.region

  template {
    template {
      service_account = google_service_account.runtime.email
      vpc_access {
        network_interfaces {
          network    = google_compute_network.private.name
          subnetwork = google_compute_subnetwork.private.name
        }
      }
      containers {
        image   = var.image_digest
        command = ["/usr/local/bin/blogravel-entrypoint"]
        env {
          name  = "CONTAINER_ROLE"
          value = "scheduler"
        }
        dynamic "env" {
          for_each = local.common_env
          content {
            name  = env.key
            value = env.value
          }
        }
        env {
          name = "APP_KEY"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.app_key.secret_id
              version = "latest"
            }
          }
        }
        env {
          name = "DB_PASSWORD"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.db_password.secret_id
              version = "latest"
            }
          }
        }
        env {
          name = "AWS_ACCESS_KEY_ID"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.aws_access_key.secret_id
              version = "latest"
            }
          }
        }
        env {
          name = "AWS_SECRET_ACCESS_KEY"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.aws_secret_access_key.secret_id
              version = "latest"
            }
          }
        }
      }
    }
  }
}

resource "google_cloud_run_v2_job" "migrate" {
  name     = "${local.name_prefix}-migrate"
  location = var.region

  template {
    template {
      service_account = google_service_account.runtime.email
      vpc_access {
        network_interfaces {
          network    = google_compute_network.private.name
          subnetwork = google_compute_subnetwork.private.name
        }
      }
      containers {
        image   = var.image_digest
        command = ["php", "artisan", "migrate", "--force"]
        dynamic "env" {
          for_each = local.common_env
          content {
            name  = env.key
            value = env.value
          }
        }
        env {
          name = "APP_KEY"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.app_key.secret_id
              version = "latest"
            }
          }
        }
        env {
          name = "DB_PASSWORD"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.db_password.secret_id
              version = "latest"
            }
          }
        }
        env {
          name = "AWS_ACCESS_KEY_ID"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.aws_access_key.secret_id
              version = "latest"
            }
          }
        }
        env {
          name = "AWS_SECRET_ACCESS_KEY"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.aws_secret_access_key.secret_id
              version = "latest"
            }
          }
        }
      }
    }
  }
}

resource "google_cloud_run_v2_service_iam_member" "web_public" {
  name     = google_cloud_run_v2_service.web.name
  location = google_cloud_run_v2_service.web.location
  role     = "roles/run.invoker"
  member   = "allUsers"
}

resource "google_cloud_run_v2_job_iam_member" "scheduler_invoker" {
  name     = google_cloud_run_v2_job.scheduler.name
  location = var.region
  role     = "roles/run.invoker"
  member   = "serviceAccount:${google_service_account.scheduler.email}"
}

resource "google_cloud_scheduler_job" "scheduler" {
  name      = "${local.name_prefix}-scheduler"
  region    = var.region
  schedule  = "* * * * *"
  time_zone = "Etc/UTC"

  http_target {
    http_method = "POST"
    uri         = "https://run.googleapis.com/v2/projects/${var.project_id}/locations/${var.region}/jobs/${google_cloud_run_v2_job.scheduler.name}:run"
    body        = base64encode("{}")
    headers = {
      Content-Type = "application/json"
    }
    oauth_token {
      service_account_email = google_service_account.scheduler.email
    }
  }
}
