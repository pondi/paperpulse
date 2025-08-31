# PaperPulse Kubernetes Deployment

This directory contains Kubernetes manifests for deploying PaperPulse using Kustomize.

## Prerequisites

- Kubernetes cluster (1.24+)
- kubectl configured to access your cluster
- Kustomize (optional, kubectl has built-in support)
- cert-manager installed (for automatic HTTPS)
- NGINX Ingress Controller installed

## Directory Structure

```
kubernetes/
├── base/                    # Base configuration
│   ├── namespace.yaml      # Namespace definition
│   ├── configmap.yaml      # Non-sensitive configuration
│   ├── secret.yaml         # Sensitive configuration template
│   ├── postgres.yaml       # PostgreSQL deployment
│   ├── redis.yaml          # Redis deployment
│   ├── meilisearch.yaml    # Meilisearch deployment
│   ├── web-deployment.yaml # Web server deployment
│   ├── worker-deployment.yaml # Queue worker deployment
│   ├── scheduler-deployment.yaml # Cron scheduler deployment
│   ├── ingress.yaml        # Ingress configuration
│   └── hpa.yaml           # Horizontal Pod Autoscaler
├── overlays/
│   └── production/         # Production-specific overrides
│       ├── kustomization.yaml
│       ├── deployment-patches.yaml
│       ├── ingress-patch.yaml
│       └── secrets.env.example
└── deploy.sh              # Deployment script
```

## Quick Start

### 1. Deploy to Development/Staging

```bash
# Deploy base configuration
kubectl apply -k base/

# Or use the deployment script
./deploy.sh --env development --namespace paperpulse-dev
```

### 2. Deploy to Production

```bash
# First, create production secrets
cd overlays/production
cp secrets.env.example secrets.env
# Edit secrets.env with production values

# Deploy production configuration
cd ../..
kubectl apply -k overlays/production/

# Or use the deployment script
./deploy.sh --env production --tag v1.0.0
```

## Configuration

### Base Configuration

The base configuration includes:
- Single namespace deployment
- All required services (PostgreSQL, Redis, Meilisearch)
- 3 web replicas, 2 worker replicas, 1 scheduler
- Basic resource limits
- Health checks and probes

### Production Overlay

The production overlay adds:
- Increased replicas (5 web, 3 workers)
- Higher resource limits
- Production domain configuration
- Stricter security settings

### Secrets Management

**Never commit actual secrets!** The `secret.yaml` contains placeholder values.

For production:
1. Copy `overlays/production/secrets.env.example` to `secrets.env`
2. Fill in actual production values
3. The kustomize secretGenerator will create the secret

Alternatively, use external secret management:
- Kubernetes Secrets Store CSI Driver
- Sealed Secrets
- External Secrets Operator

## Scaling

### Manual Scaling
```bash
# Scale web deployment
kubectl -n paperpulse scale deployment paperpulse-web --replicas=5

# Scale workers
kubectl -n paperpulse scale deployment paperpulse-worker --replicas=4
```

### Automatic Scaling
HPA is configured to automatically scale based on CPU and memory:
- Web: 3-10 replicas (70% CPU, 80% memory)
- Workers: 2-8 replicas (80% CPU, 80% memory)

## Monitoring

```bash
# Check deployment status
kubectl -n paperpulse get deployments

# View pods
kubectl -n paperpulse get pods

# Check logs
kubectl -n paperpulse logs -f deployment/paperpulse-web
kubectl -n paperpulse logs -f deployment/paperpulse-worker

# View Horizon dashboard (port-forward)
kubectl -n paperpulse port-forward deployment/paperpulse-web 8000:8000
# Visit http://localhost:8000/horizon
```

## Updating

```bash
# Update image tags
cd kubernetes
kustomize edit set image paperpulse/web:v1.0.1 paperpulse/worker:v1.0.1

# Apply updates
kubectl apply -k base/

# Or use deployment script
./deploy.sh --tag v1.0.1
```

## Troubleshooting

### Database Connection Issues
```bash
# Check database pod
kubectl -n paperpulse logs deployment/postgres

# Test connection
kubectl -n paperpulse exec -it deployment/paperpulse-web -- php artisan db:monitor
```

### Migration Issues
```bash
# Run migrations manually
kubectl -n paperpulse exec -it deployment/paperpulse-web -- php artisan migrate --force
```

### Storage Issues
For production, consider:
- Using cloud provider's managed databases
- Persistent volumes with proper StorageClass
- Regular backups

## Security Notes

1. **Secrets**: Use proper secret management in production
2. **Network Policies**: Consider adding NetworkPolicies for pod-to-pod communication
3. **RBAC**: Limit service account permissions
4. **Pod Security**: Use SecurityContext and PodSecurityPolicies
5. **Image Scanning**: Scan container images for vulnerabilities

## Production Checklist

- [ ] Configure proper domain in ingress
- [ ] Set up cert-manager for HTTPS
- [ ] Configure external database (RDS, Cloud SQL, etc.)
- [ ] Set up monitoring (Prometheus, Grafana)
- [ ] Configure log aggregation (ELK, Loki)
- [ ] Set up backups for persistent data
- [ ] Configure secret management
- [ ] Review and adjust resource limits
- [ ] Set up alerts for critical metrics
- [ ] Test disaster recovery procedures