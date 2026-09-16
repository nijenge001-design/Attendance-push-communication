```dotenv
# ==============================================================================
# Logging improvements – suggested additions / overrides
# Merge these into your main .env (or keep the values you already have)
# ==============================================================================

# --- Request logging ---
LOG_REQUESTS=true
LOG_REQUESTS_TO_FILE=true
LOG_REQUEST_ONLY_PATHS=iclock/*
LOG_REQUEST_MAX_BODY=2097152
LOG_REQUEST_CHANNEL=stack
LOG_REQUEST_LEVEL=debug
LOG_REQUEST_QUEUE=default
LOG_REQUEST_COMPRESS=false
LOG_REQUEST_RESPONSE=true
LOG_REQUEST_RETENTION_DAYS=14
LOG_REQUEST_MAX_STRING=2000
LOG_REQUEST_MAX_HEADER=500
# LOG_REQUEST_INCLUDE_BODY=true
# LOG_REQUEST_MAX_KEYS=50

# --- Device logging ---
LOG_DEVICE=true
LOG_DEVICE_PER_FILE=false
LOG_DEVICE_DAYS=7
LOG_DEVICE_LEVEL=debug
LOG_DEVICE_MAX_CONTEXT=2000
DEVICE_DEBUG_SN=
DEVICE_DEBUG_DUMP=false

```
