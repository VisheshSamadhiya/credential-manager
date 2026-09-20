pipeline {
    agent any

    environment {
        DEPLOY_SERVER  = '172.31.42.19'
        DEPLOY_USER    = 'ubuntu'

        APP_NAME       = 'credential-manager'
        CONTAINER_NAME = 'credential-manager'

        HOST_PORT      = '8088'
        CONTAINER_PORT = '80'

        DEPLOY_PATH    = '/opt/credential-manager'

        IMAGE_NAME     = 'credential-manager'
        IMAGE_TAG      = "${BUILD_NUMBER}"
    }

    stages {

        stage('Clean Workspace') {
            steps {
                echo 'Cleaning Jenkins workspace...'
                deleteDir()
            }
        }

        stage('Checkout') {
            steps {
                echo 'Checking out source code...'

                checkout scm
            }
        }

        stage('Check Files') {
            steps {
                sh '''
                    set -e

                    echo "===== Repository Files ====="
                    ls -la

                    echo ""
                    echo "===== Checking Dockerfile ====="

                    if [ -f Dockerfile ]; then
                        echo "Dockerfile found."
                    else
                        echo "ERROR: Dockerfile not found."
                        exit 1
                    fi

                    echo ""
                    echo "===== Checking Jenkinsfile ====="

                    if [ -f Jenkinsfile ]; then
                        echo "Jenkinsfile found."
                    else
                        echo "ERROR: Jenkinsfile not found."
                        exit 1
                    fi
                '''
            }
        }

        stage('Test SSH Connection') {
            steps {
                withCredentials([
                    sshUserPrivateKey(
                        credentialsId: 'ec-2',
                        keyFileVariable: 'SSH_KEY',
                        usernameVariable: 'SSH_USER'
                    )
                ]) {
                    sh '''
                        set -e

                        echo "Testing SSH connection to EC2..."

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=no \
                            -o UserKnownHostsFile=/dev/null \
                            -o IdentitiesOnly=yes \
                            -o BatchMode=yes \
                            -o ConnectTimeout=15 \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "echo 'SSH CONNECTION SUCCESSFUL' && hostname"
                    '''
                }
            }
        }

        stage('Check EC2 Docker') {
            steps {
                withCredentials([
                    sshUserPrivateKey(
                        credentialsId: 'ec-2',
                        keyFileVariable: 'SSH_KEY',
                        usernameVariable: 'SSH_USER'
                    )
                ]) {
                    sh '''
                        set -e

                        echo "Checking Docker on EC2..."

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=no \
                            -o UserKnownHostsFile=/dev/null \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "docker --version && docker info >/dev/null && echo 'Docker is working correctly.'"
                    '''
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                sh '''
                    set -e

                    echo "===== Building Docker Image ====="

                    docker build \
                        -t "${IMAGE_NAME}:${IMAGE_TAG}" \
                        -t "${IMAGE_NAME}:latest" \
                        .

                    echo ""
                    echo "===== Docker Images ====="

                    docker images "${IMAGE_NAME}"

                    echo ""
                    echo "Docker image built successfully."
                '''
            }
        }

        stage('Test Docker Image') {
            steps {
                sh '''
                    set -e

                    TEST_CONTAINER="${APP_NAME}-test"

                    echo "===== Starting Temporary Test Container ====="

                    docker rm -f "$TEST_CONTAINER" 2>/dev/null || true

                    docker run -d \
                        --name "$TEST_CONTAINER" \
                        -p 8099:${CONTAINER_PORT} \
                        "${IMAGE_NAME}:${IMAGE_TAG}"

                    echo "Waiting for application to start..."
                    sleep 10

                    echo "===== Testing Application ====="

                    curl \
                        --fail \
                        --silent \
                        --show-error \
                        http://127.0.0.1:8099/ \
                        > /dev/null

                    echo "Application test PASSED."

                    docker rm -f "$TEST_CONTAINER"
                '''
            }
        }

        stage('Trivy Security Scan') {
            steps {
                sh '''
                    if command -v trivy >/dev/null 2>&1; then

                        echo "===== Running Trivy Security Scan ====="

                        trivy \
                            --config /dev/null \
                            --ignorefile /dev/null \
                            --scanners vuln \
                            image \
                            --exit-code 0 \
                            --severity HIGH,CRITICAL \
                            "${IMAGE_NAME}:${IMAGE_TAG}"

                        echo "Trivy vulnerability scan completed."

                    else

                        echo "ERROR: Trivy is not installed on Jenkins."
                        exit 1

                    fi
                '''
            }
        }

        stage('Export Docker Image') {
            steps {
                sh '''
                    set -e

                    echo "===== Exporting Docker Image ====="

                    docker save "${IMAGE_NAME}:${IMAGE_TAG}" \
                        | gzip > "${IMAGE_NAME}-${IMAGE_TAG}.tar.gz"

                    echo "Image export completed."

                    ls -lh "${IMAGE_NAME}-${IMAGE_TAG}.tar.gz"
                '''
            }
        }

        stage('Copy Image to EC2') {
            steps {
                withCredentials([
                    sshUserPrivateKey(
                        credentialsId: 'ec-2',
                        keyFileVariable: 'SSH_KEY',
                        usernameVariable: 'SSH_USER'
                    )
                ]) {
                    sh '''
                        set -e

                        echo "===== Preparing EC2 Deployment Directory ====="

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=no \
                            -o UserKnownHostsFile=/dev/null \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "mkdir -p '$DEPLOY_PATH'"

                        echo "===== Copying Docker Image to EC2 ====="

                        scp \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=no \
                            -o UserKnownHostsFile=/dev/null \
                            -o IdentitiesOnly=yes \
                            "${IMAGE_NAME}-${IMAGE_TAG}.tar.gz" \
                            "$SSH_USER@$DEPLOY_SERVER:$DEPLOY_PATH/"

                        echo "Docker image copied successfully."
                    '''
                }
            }
        }

        stage('Deploy to EC2') {
            steps {
                withCredentials([
                    sshUserPrivateKey(
                        credentialsId: 'ec-2',
                        keyFileVariable: 'SSH_KEY',
                        usernameVariable: 'SSH_USER'
                    )
                ]) {
                    sh '''
                        set -e

                        echo "===== Deploying Application to EC2 ====="

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=no \
                            -o UserKnownHostsFile=/dev/null \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" << EOF

set -e

echo "===== Loading Docker Image ====="

gunzip -c "$DEPLOY_PATH/${IMAGE_NAME}-${IMAGE_TAG}.tar.gz" | docker load

echo "===== Removing Previous Container ====="

docker rm -f "$CONTAINER_NAME" 2>/dev/null || true

echo "===== Starting New Container ====="

docker run -d \
    --name "$CONTAINER_NAME" \
    --restart unless-stopped \
    -p "$HOST_PORT:$CONTAINER_PORT" \
    "$IMAGE_NAME:$IMAGE_TAG"

echo "===== Container Status ====="

docker ps --filter "name=$CONTAINER_NAME"

echo "===== Removing Deployment Archive ====="

rm -f "$DEPLOY_PATH/${IMAGE_NAME}-${IMAGE_TAG}.tar.gz"

echo "Deployment completed successfully."

EOF
                    '''
                }
            }
        }

        stage('Health Check') {
            steps {
                withCredentials([
                    sshUserPrivateKey(
                        credentialsId: 'ec-2',
                        keyFileVariable: 'SSH_KEY',
                        usernameVariable: 'SSH_USER'
                    )
                ]) {
                    sh '''
                        set -e

                        echo "===== Waiting for Application ====="

                        sleep 10

                        echo "===== Running EC2 Health Check ====="

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=no \
                            -o UserKnownHostsFile=/dev/null \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "curl --fail --silent --show-error http://127.0.0.1:${HOST_PORT}/ > /dev/null"

                        echo ""
                        echo "=========================================="
                        echo "APPLICATION HEALTH CHECK PASSED"
                        echo "=========================================="
                    '''
                }
            }
        }
    }

    post {

        success {
            echo """
==========================================
CI/CD PIPELINE SUCCESSFUL
==========================================

Application : ${APP_NAME}
Build       : ${BUILD_NUMBER}
Image       : ${IMAGE_NAME}:${IMAGE_TAG}
Server      : ${DEPLOY_SERVER}
Port        : ${HOST_PORT}

Deployment completed successfully.
"""
        }

        failure {
            echo """
==========================================
CI/CD PIPELINE FAILED
==========================================

Build ${BUILD_NUMBER} failed.

Check the failed stage in the Jenkins console.
"""
        }

        always {
            sh '''
                echo "Cleaning Jenkins temporary resources..."

                docker rm -f "${APP_NAME}-test" 2>/dev/null || true

                docker rmi "${IMAGE_NAME}:${IMAGE_TAG}" 2>/dev/null || true

                rm -f "${IMAGE_NAME}-${IMAGE_TAG}.tar.gz" 2>/dev/null || true
            '''
        }
    }
}
