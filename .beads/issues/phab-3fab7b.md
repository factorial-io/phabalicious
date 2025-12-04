---
title: Add scotty authentication preflight check
status: open
priority: 2
issue_type: task
created_at: 2025-12-04T14:39:09.343790+00:00
updated_at: 2025-12-04T14:39:09.343790+00:00
---

# Description

Implement preflightTask in ScottyMethod to verify authentication using app:list command before executing scotty operations. This catches expired tokens that auth:status incorrectly reports as valid.
