pipeline {
    agent any

    parameters {
        choice(
            name: 'DEPLOYMENT_MODE',
            choices: [
                'NORMAL_DEPLOYMENT',
                'ROLLBACK_TEST'
            ],
            description: 'NORMAL_DEPLOYMENT deploys normally. ROLLBACK_TEST deliberately fails the new release health check to verify automatic application rollback.'
        )
    }

    environment {
        DEPLOY_SERVER  = '172.31.42.19'
        DEPLOY_USER    = 'ubuntu'

        APP_NAME       = 'credential-manager'
        CONTAINER_NAME = 'credential-manager'

        HOST_PORT      = '8088'
        CONTAINER_PORT = '80'

        DEPLOY_PATH    = '/opt/credential-manager'

        IMAGE_NAME     = 'credential-manager'

        SSH_KNOWN_HOSTS = '/var/lib/jenkins/.ssh/known_hosts'
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

        stage('Show Deployment Mode') {
            steps {
                echo """
==========================================
DEPLOYMENT MODE
==========================================

Mode : ${params.DEPLOYMENT_MODE}

==========================================
"""
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
                            -o StrictHostKeyChecking=yes \
                            -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
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
                            -o StrictHostKeyChecking=yes \
                            -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "docker --version && docker info >/dev/null && echo 'Docker is working correctly.'"
                    '''
                }
            }
        }

        stage('Determine Release') {
            steps {
                script {

                    withCredentials([
                        sshUserPrivateKey(
                            credentialsId: 'ec-2',
                            keyFileVariable: 'SSH_KEY',
                            usernameVariable: 'SSH_USER'
                        )
                    ]) {

                        def state = sh(
                            script: '''
                                ssh \
                                    -i "$SSH_KEY" \
                                    -o StrictHostKeyChecking=yes \
                                    -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                                    -o IdentitiesOnly=yes \
                                    "$SSH_USER@$DEPLOY_SERVER" \
                                    "cat '$DEPLOY_PATH/release-state.env'"
                            ''',
                            returnStdout: true
                        ).trim()

                        echo """
===== CURRENT RELEASE STATE =====

${state}

==================================
"""

                        def currentRelease = sh(
                            script: """
                                printf '%s\\n' '${state}' |
                                awk -F= '/^CURRENT_RELEASE=/ {print \$2}'
                            """,
                            returnStdout: true
                        ).trim()

                        def currentImage = sh(
                            script: """
                                printf '%s\\n' '${state}' |
                                awk -F= '/^CURRENT_IMAGE=/ {print \$2}'
                            """,
                            returnStdout: true
                        ).trim()

                        if (!currentRelease || !currentRelease.isInteger()) {
                            error("Invalid CURRENT_RELEASE in release-state.env: '${currentRelease}'")
                        }

                        if (!currentImage) {
                            error("CURRENT_IMAGE is empty in release-state.env")
                        }

                        def newRelease = currentRelease.toInteger() + 1

                        env.CURRENT_RELEASE = currentRelease
                        env.CURRENT_IMAGE = currentImage
                        env.NEW_RELEASE = newRelease.toString()
                        env.IMAGE_TAG = newRelease.toString()

                        echo """
==========================================
RELEASE CALCULATION
==========================================

Current Release : ${env.CURRENT_RELEASE}
Current Image   : ${env.CURRENT_IMAGE}

New Release     : ${env.NEW_RELEASE}
New Image       : ${env.IMAGE_NAME}:${env.IMAGE_TAG}

==========================================
"""
                    }
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
                            -o StrictHostKeyChecking=yes \
                            -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "mkdir -p '$DEPLOY_PATH/releases' '$DEPLOY_PATH/manifests' '$DEPLOY_PATH/scripts'"

                        echo "===== Copying Docker Image to EC2 ====="

                        scp \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=yes \
                            -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                            -o IdentitiesOnly=yes \
                            "${IMAGE_NAME}-${IMAGE_TAG}.tar.gz" \
                            "$SSH_USER@$DEPLOY_SERVER:$DEPLOY_PATH/releases/"

                        echo "Docker image copied successfully."
                    '''
                }
            }
        }
         
        stage('Deploy With Automatic Rollback') {
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

                echo ""
                echo "=========================================="
                echo "DEPLOYMENT START"
                echo "=========================================="
                echo ""

                echo "Jenkins deployment variables:"
                echo "DEPLOYMENT_MODE=${DEPLOYMENT_MODE}"
                echo "CURRENT_RELEASE=${CURRENT_RELEASE}"
                echo "CURRENT_IMAGE=${CURRENT_IMAGE}"
                echo "NEW_RELEASE=${NEW_RELEASE}"
                echo "IMAGE_NAME=${IMAGE_NAME}"
                echo "IMAGE_TAG=${IMAGE_TAG}"

                IMAGE_ARCHIVE="${IMAGE_NAME}-${IMAGE_TAG}.tar.gz"

                echo ""
                echo "Expected archive:"
                echo "${IMAGE_ARCHIVE}"

                if [ ! -f "${IMAGE_ARCHIVE}" ]; then
                    echo ""
                    echo "ERROR: Deployment archive does not exist:"
                    echo "${IMAGE_ARCHIVE}"
                    exit 1
                fi

                echo ""
                echo "Archive verified:"
                ls -lh "${IMAGE_ARCHIVE}"

                echo ""
                echo "=========================================="
                echo "REMOTE DEPLOYMENT"
                echo "=========================================="

                ssh \
                    -i "$SSH_KEY" \
                    -o StrictHostKeyChecking=yes \
                    -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                    -o IdentitiesOnly=yes \
                    "$SSH_USER@$DEPLOY_SERVER" \
                    "DEPLOY_PATH='${DEPLOY_PATH}' \
                     APP_NAME='${APP_NAME}' \
                     CONTAINER_NAME='${CONTAINER_NAME}' \
                     IMAGE_NAME='${IMAGE_NAME}' \
                     IMAGE_TAG='${IMAGE_TAG}' \
                     HOST_PORT='${HOST_PORT}' \
                     CONTAINER_PORT='${CONTAINER_PORT}' \
                     CURRENT_RELEASE='${CURRENT_RELEASE}' \
                     CURRENT_IMAGE='${CURRENT_IMAGE}' \
                     NEW_RELEASE='${NEW_RELEASE}' \
                     DEPLOYMENT_MODE='${DEPLOYMENT_MODE}' \
                     bash -s" <<'REMOTE_SCRIPT'

set -u

NEW_IMAGE="${IMAGE_NAME}:${IMAGE_TAG}"

IMAGE_ARCHIVE="${DEPLOY_PATH}/releases/${IMAGE_NAME}-${IMAGE_TAG}.tar.gz"

STATE_FILE="${DEPLOY_PATH}/release-state.env"

MANIFEST_FILE="${DEPLOY_PATH}/manifests/release-${NEW_RELEASE}.env"

echo ""
echo "=========================================="
echo "REMOTE DEPLOYMENT INFORMATION"
echo "=========================================="

echo "Deployment Mode : ${DEPLOYMENT_MODE}"
echo "Current Release : ${CURRENT_RELEASE}"
echo "Current Image   : ${CURRENT_IMAGE}"
echo "New Release     : ${NEW_RELEASE}"
echo "New Image       : ${NEW_IMAGE}"
echo "Image Archive   : ${IMAGE_ARCHIVE}"

echo ""
echo "=========================================="
echo "Loading New Docker Image"
echo "=========================================="

if ! gunzip -c "${IMAGE_ARCHIVE}" | docker load; then

    echo ""
    echo "ERROR: Docker image load failed."
    echo "Current release has NOT been modified."

    exit 1
fi

echo ""
echo "Docker image loaded successfully."

echo ""
echo "=========================================="
echo "Verifying New Docker Image"
echo "=========================================="

if ! docker image inspect "${NEW_IMAGE}" >/dev/null 2>&1; then

    echo "ERROR: New Docker image does not exist after docker load."

    exit 1
fi

echo "New Docker image verified."

echo ""
echo "=========================================="
echo "Preserving Previous Release"
echo "=========================================="

if ! docker image inspect "${CURRENT_IMAGE}" >/dev/null 2>&1; then

    echo "ERROR: Previous release image '${CURRENT_IMAGE}' does not exist."
    echo "Deployment stopped for safety."

    exit 1
fi

echo "Previous release image verified:"
echo "${CURRENT_IMAGE}"

echo ""
echo "=========================================="
echo "Stopping Current Container"
echo "=========================================="

docker rm -f "${CONTAINER_NAME}" 2>/dev/null || true

echo "Current container stopped."

echo ""
echo "=========================================="
echo "Starting New Release"
echo "=========================================="

NEW_CONTAINER_STARTED=0

if docker run -d \
    --name "${CONTAINER_NAME}" \
    --restart unless-stopped \
    -p "${HOST_PORT}:${CONTAINER_PORT}" \
    "${NEW_IMAGE}"; then

    NEW_CONTAINER_STARTED=1

    echo "New container started successfully."

else

    echo "ERROR: New container failed to start."

fi

echo ""
echo "=========================================="
echo "Application Health Check"
echo "=========================================="

HEALTH_OK=0

if [ "${NEW_CONTAINER_STARTED}" -eq 1 ]; then

    echo "Waiting for application to initialize..."

    sleep 10

    if curl \
        --fail \
        --silent \
        --show-error \
        "http://127.0.0.1:${HOST_PORT}/" \
        > /dev/null; then

        HEALTH_OK=1

        echo "APPLICATION HEALTH CHECK PASSED."

    else

        echo "APPLICATION HEALTH CHECK FAILED."

    fi

else

    echo "Health check skipped because container did not start."

fi

echo ""
echo "=========================================="
echo "ROLLBACK DECISION"
echo "=========================================="

if [ "${DEPLOYMENT_MODE}" = "ROLLBACK_TEST" ]; then

    echo ""
    echo "ROLLBACK_TEST mode enabled."
    echo "Deliberately forcing health-check failure."
    echo ""

    HEALTH_OK=0
fi

if [ "${HEALTH_OK}" -eq 1 ]; then

    echo ""
    echo "=========================================="
    echo "NEW RELEASE HEALTHY"
    echo "=========================================="

    echo "Updating release state..."

    cat > "${STATE_FILE}" << STATE_EOF
CURRENT_RELEASE=${NEW_RELEASE}
CURRENT_IMAGE=${NEW_IMAGE}
PREVIOUS_RELEASE=${CURRENT_RELEASE}
PREVIOUS_IMAGE=${CURRENT_IMAGE}
STATE_EOF

    echo "Release state updated."

    echo ""
    echo "Creating release manifest..."

    cat > "${MANIFEST_FILE}" << MANIFEST_EOF
RELEASE=${NEW_RELEASE}
IMAGE=${NEW_IMAGE}
PREVIOUS_RELEASE=${CURRENT_RELEASE}
PREVIOUS_IMAGE=${CURRENT_IMAGE}
DEPLOYMENT_MODE=${DEPLOYMENT_MODE}
DEPLOYMENT_TIMESTAMP=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
MANIFEST_EOF

    echo "Release manifest created:"
    cat "${MANIFEST_FILE}"

    echo ""
    echo "Removing deployment archive..."

    rm -f "${IMAGE_ARCHIVE}"

    echo ""
    echo "=========================================="
    echo "DEPLOYMENT SUCCESSFUL"
    echo "=========================================="

    echo "Active Release : ${NEW_RELEASE}"
    echo "Active Image   : ${NEW_IMAGE}"

    exit 0
fi

echo ""
echo "=========================================="
echo "AUTOMATIC APPLICATION ROLLBACK"
echo "=========================================="

echo "New release failed health validation."
echo "Removing failed release..."

docker rm -f "${CONTAINER_NAME}" 2>/dev/null || true

echo ""
echo "Starting previous release..."

if ! docker run -d \
    --name "${CONTAINER_NAME}" \
    --restart unless-stopped \
    -p "${HOST_PORT}:${CONTAINER_PORT}" \
    "${CURRENT_IMAGE}"; then

    echo ""
    echo "CRITICAL ERROR:"
    echo "Previous release failed to start."
    echo ""
    echo "Manual intervention is required."

    exit 2
fi

echo "Previous release container started."

echo ""
echo "Waiting for previous release..."

sleep 10

echo ""
echo "Verifying previous release health..."

if ! curl \
    --fail \
    --silent \
    --show-error \
    "http://127.0.0.1:${HOST_PORT}/" \
    > /dev/null; then

    echo ""
    echo "CRITICAL ERROR:"
    echo "Previous release failed health check."
    echo ""
    echo "Manual intervention is required."

    exit 3
fi

echo ""
echo "=========================================="
echo "ROLLBACK HEALTH CHECK PASSED"
echo "=========================================="

echo "Previous release is healthy."

echo ""
echo "Verifying release state..."

echo "Expected current release : ${CURRENT_RELEASE}"

if grep -q "^CURRENT_RELEASE=${CURRENT_RELEASE}$" "${STATE_FILE}"; then

    echo "Release state remains on previous release."

else

    echo "ERROR: Release state does not match expected previous release."

    exit 4
fi

echo ""
echo "Removing failed deployment archive..."

rm -f "${IMAGE_ARCHIVE}"

echo ""
echo "=========================================="
echo "ROLLBACK COMPLETE"
echo "=========================================="

echo "Restored Release : ${CURRENT_RELEASE}"
echo "Restored Image   : ${CURRENT_IMAGE}"

if [ "${DEPLOYMENT_MODE}" = "ROLLBACK_TEST" ]; then

    echo ""
    echo "=========================================="
    echo "ROLLBACK TEST PASSED"
    echo "=========================================="

    echo "The new release was deliberately failed."
    echo "Automatic application rollback succeeded."
    echo "Previous release is healthy."
    echo "Release state was preserved."

    exit 10
fi

echo ""
echo "Deployment failed and previous release was restored."

exit 1

REMOTE_SCRIPT
            '''
        }
    }
}
        stage('Verify Production Release') {
            when {
                expression {
                    params.DEPLOYMENT_MODE == 'NORMAL_DEPLOYMENT'
                }
            }

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

                        echo ""
                        echo "=========================================="
                        echo "VERIFYING PRODUCTION RELEASE"
                        echo "=========================================="

                        echo ""
                        echo "===== Release State ====="

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=yes \
                            -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "cat '$DEPLOY_PATH/release-state.env'"

                        echo ""
                        echo "===== Running Container ====="

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=yes \
                            -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "docker ps --filter 'name=$CONTAINER_NAME'"

                        echo ""
                        echo "===== Production Health ====="

                        ssh \
                            -i "$SSH_KEY" \
                            -o StrictHostKeyChecking=yes \
                            -o UserKnownHostsFile="$SSH_KNOWN_HOSTS" \
                            -o IdentitiesOnly=yes \
                            "$SSH_USER@$DEPLOY_SERVER" \
                            "curl --fail --silent --show-error http://127.0.0.1:${HOST_PORT}/ > /dev/null"

                        echo ""
                        echo "=========================================="
                        echo "PRODUCTION VERIFICATION PASSED"
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

Deployment Mode : ${params.DEPLOYMENT_MODE}

Release     : ${env.NEW_RELEASE}
Image       : ${env.IMAGE_NAME}:${env.IMAGE_TAG}

Server      : ${env.DEPLOY_SERVER}
Port        : ${env.HOST_PORT}

Deployment completed successfully.
"""
        }

        failure {
            echo """
==========================================
CI/CD PIPELINE RESULT
==========================================

Build ${BUILD_NUMBER} failed.

Deployment Mode : ${params.DEPLOYMENT_MODE}

If this was ROLLBACK_TEST:
this FAILURE is EXPECTED because the pipeline
deliberately exits non-zero after successfully
performing and verifying the rollback.

Check the Jenkins console for:

ROLLBACK TEST PASSED

If that message exists, the rollback test succeeded.
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
