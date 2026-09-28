variable "project_id" {
  type = string
}

variable "environment" {
  type    = string
  default = "production"
}

variable "region" {
  type    = string
  default = "us-central1"
}

variable "image_digest" {
  type        = string
  description = "Fully-qualified Artifact Registry image digest."
}

variable "domain" {
  type        = string
  description = "Root domain used by the application."
}

variable "db_name" {
  type    = string
  default = "blogravel"
}

variable "db_user" {
  type    = string
  default = "blogravel"
}

variable "db_password_secret_id" {
  type        = string
  description = "Secret Manager secret ID containing the Cloud SQL password."
}

variable "aws_access_key_secret_id" {
  type        = string
  default     = "blogravel-storage-access-key"
  description = "Secret Manager secret ID containing the GCS S3-compatible access key."
}

variable "aws_secret_access_key_secret_id" {
  type        = string
  default     = "blogravel-storage-secret-key"
  description = "Secret Manager secret ID containing the GCS S3-compatible secret key."
}

variable "app_key_secret_id" {
  type        = string
  description = "Secret Manager secret ID containing APP_KEY."
}

variable "artifact_repository" {
  type    = string
  default = "blogravel"
}

variable "storage_bucket_name" {
  type        = string
  description = "Globally unique media and backup bucket name."
}
