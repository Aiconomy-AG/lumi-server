# Handover checklist

## Access and ownership

- Repository and deployment-platform ownership transferred
- Production environment and secret-management access transferred separately from this public documentation
- Shopify, Firebase, mail, Gemini, Meilisearch, database, and domain ownership confirmed
- On-call, incident, backup, and recovery responsibilities assigned

## Technical verification

- Application and documentation builds pass from a clean checkout
- Database backup and restore procedure tested
- Queue worker, scheduler, and Reverb supervision verified
- Failed-job and application-log access verified
- Shopify webhooks and app proxy verified against the deployed API version
- Password reset, invitations, push notifications, and AI feature flags verified
- Known incomplete behavior, including scaffolded workspace-resource routes, entered into the maintenance backlog

## Release readiness

- Current production revision and rollback constraints recorded
- Environment-variable inventory compared with deployment configuration
- Outstanding migrations and data-repair scripts reviewed
- Monitoring and alert destinations transferred
- API consumers informed of contract ownership and versioning process

