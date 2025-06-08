#!/bin/bash
# PaperPulse Kubernetes Deployment Script

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
ENVIRONMENT="production"
NAMESPACE="paperpulse"
IMAGE_TAG="latest"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --env)
            ENVIRONMENT="$2"
            shift 2
            ;;
        --tag)
            IMAGE_TAG="$2"
            shift 2
            ;;
        --namespace)
            NAMESPACE="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo -e "${GREEN}Deploying PaperPulse to Kubernetes${NC}"
echo "Environment: $ENVIRONMENT"
echo "Namespace: $NAMESPACE"
echo "Image Tag: $IMAGE_TAG"

# Check if kubectl is installed
if ! command -v kubectl &> /dev/null; then
    echo -e "${RED}kubectl is not installed${NC}"
    exit 1
fi

# Check if kustomize is installed
if ! command -v kustomize &> /dev/null; then
    echo -e "${YELLOW}kustomize is not installed, using kubectl -k${NC}"
    KUSTOMIZE_CMD="kubectl kustomize"
else
    KUSTOMIZE_CMD="kustomize build"
fi

# Check cluster connection
echo "Checking cluster connection..."
if ! kubectl cluster-info &> /dev/null; then
    echo -e "${RED}Cannot connect to Kubernetes cluster${NC}"
    exit 1
fi

# Build and apply based on environment
if [ "$ENVIRONMENT" == "production" ]; then
    echo "Deploying production configuration..."
    cd overlays/production
    
    # Check if secrets.env exists
    if [ ! -f "secrets.env" ]; then
        echo -e "${RED}secrets.env not found in overlays/production/${NC}"
        echo "Please copy secrets.env.example to secrets.env and fill in values"
        exit 1
    fi
    
    # Update image tags
    cd ../..
    $KUSTOMIZE_CMD overlays/production | \
        sed "s|paperpulse/web:latest|paperpulse/web:${IMAGE_TAG}|g" | \
        sed "s|paperpulse/worker:latest|paperpulse/worker:${IMAGE_TAG}|g" | \
        sed "s|paperpulse/scheduler:latest|paperpulse/scheduler:${IMAGE_TAG}|g" | \
        kubectl apply -f -
else
    echo "Deploying base configuration..."
    $KUSTOMIZE_CMD base | \
        sed "s|paperpulse/web:latest|paperpulse/web:${IMAGE_TAG}|g" | \
        sed "s|paperpulse/worker:latest|paperpulse/worker:${IMAGE_TAG}|g" | \
        sed "s|paperpulse/scheduler:latest|paperpulse/scheduler:${IMAGE_TAG}|g" | \
        kubectl apply -f -
fi

# Wait for deployments
echo -e "${YELLOW}Waiting for deployments to be ready...${NC}"
kubectl -n $NAMESPACE wait --for=condition=available --timeout=300s deployment/paperpulse-web
kubectl -n $NAMESPACE wait --for=condition=available --timeout=300s deployment/paperpulse-worker
kubectl -n $NAMESPACE wait --for=condition=available --timeout=300s deployment/paperpulse-scheduler

# Show deployment status
echo -e "${GREEN}Deployment complete!${NC}"
kubectl -n $NAMESPACE get deployments
kubectl -n $NAMESPACE get pods
kubectl -n $NAMESPACE get services
kubectl -n $NAMESPACE get ingress

echo -e "${GREEN}PaperPulse has been deployed successfully!${NC}"