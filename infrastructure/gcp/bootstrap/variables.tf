variable "project_id" {
  type        = string
  description = "GCP project that owns the Terraform state bucket."
}

variable "region" {
  type        = string
  description = "Default GCP region."
  default     = "us-central1"
}

variable "state_bucket_name" {
  type        = string
  description = "Globally unique private bucket name for Terraform state."
}

variable "state_bucket_location" {
  type        = string
  description = "Bucket location for Terraform state."
  default     = "us-central1"
}
