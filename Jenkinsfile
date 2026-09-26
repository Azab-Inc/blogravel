pipeline {
    agent {
        label 'php85'
    }

    options {
        disableConcurrentBuilds()
        timestamps()
    }

    stages {
        stage('Install PHP dependencies') {
            steps {
                dir('blogravel') {
                    sh 'composer install --no-interaction --prefer-dist --no-progress'
                }
            }
        }

        stage('Install frontend dependencies') {
            steps {
                dir('blogravel') {
                    sh 'npm ci'
                }
            }
        }

        stage('Quality checks') {
            steps {
                dir('blogravel') {
                    sh 'composer lint:check'
                    sh 'php artisan test --compact'
                    sh 'npm run build'
                }
            }
        }

        stage('Validate Compose configuration') {
            steps {
                dir('blogravel') {
                    sh 'docker compose --env-file .env.example config --quiet'
                }
            }
        }
    }

    post {
        always {
            dir('blogravel') {
                sh 'docker compose --env-file .env.example down --volumes --remove-orphans || true'
            }
            archiveArtifacts artifacts: 'blogravel/storage/logs/*.log', allowEmptyArchive: true
        }
    }
}
