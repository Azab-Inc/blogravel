output "web_url" {
  value = google_cloud_run_v2_service.web.uri
}

output "worker_service" {
  value = google_cloud_run_v2_service.worker.name
}

output "scheduler_job" {
  value = google_cloud_run_v2_job.scheduler.name
}

output "media_backups_bucket" {
  value = google_storage_bucket.media_backups.name
}
