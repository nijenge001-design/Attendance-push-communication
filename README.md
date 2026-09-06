# Attendance PUSH Communication – Laravel Application

A Laravel-based server implementation of the **ZKTeco Attendance PUSH Communication Protocol** (PUSH SDK v2.4.2 / Doc Version 4.8).

This application acts as the server side of the HTTP-based Push protocol used by ZKTeco attendance and access control devices. Devices actively push data (attendance records, biometrics, photos, etc.) and poll the server for commands.

## Protocol Overview

The Push protocol is built on top of HTTP/1.1 over TCP/IP. Key characteristics:

- **Client-initiated** – All communication is started by the device.
- **Active upload** of new data (attendance logs, photos, templates…).
- **Breakpoint resume** via timestamp stamps.
- Supports **standard attendance**, **personal identification (PID)**, and **information screen** sub-protocols.
- Optional **communication encryption** (public-key + factor exchange).
- **Hybrid biometric identification** (fingerprint, face, finger-vein, palm, visible-light face, etc.).

Supported servers historically include WDMS, ZKECO, ZKNET, ZKBioSecurity 3.0 and third-party systems (e.g. ESSL).

## Features Implemented

| Feature | Endpoint / Mechanism | Status |
|---------|----------------------|--------|
| Initialization Information Exchange | `GET /iclock/cdata?SN=...&options=all` | ✅ |
| Public Key Exchange | `POST /iclock/exchange?type=publickey` | ✅ |
| Factor Exchange | `POST /iclock/exchange?type=factors` | ✅ |
| Push Configuration Information | `POST /iclock/cdata?table=options` | ✅ |
| Heartbeat / Ping | Supported via `SupportPing` | ✅ |
| Upload Attendance Record | `POST /iclock/cdata?table=ATTLOG` | ✅ |
| Upload Attendance Photo | `POST /iclock/cdata?table=ATTPHOTO` | ✅ |
| Upload Operation Log | `POST /iclock/cdata?table=OPERLOG` | ✅ |
| Upload User Information | `POST /iclock/cdata?table=USER` | ✅ |
| Upload Fingerprint / Face / Unified Templates | `POST /iclock/cdata?table=BIODATA` / `template` | ✅ |
| Upload Comparison Photo | `POST /iclock/cdata?table=BIOPHOTO` | ✅ |
| Upload Error Log | `POST /iclock/cdata?table=ERRORLOG` | ✅ |
| Get Command (polling) | `GET /iclock/getrequest?SN=...` | ✅ |
| Command Reply | `POST /iclock/devicecmd?SN=...` | ✅ |
| Data Commands (UPDATE / DELETE / QUERY) | via Get Command | ✅ |
| Clear Commands | via Get Command | ✅ |
| Remote Enrollment | via Get Command | ✅ |
| Online Upgrade | via Get Command | ✅ |
| Background Verification | via Get Command | ✅ |
| Remote Attendance | Supported | ✅ |

## Requirements

- PHP 8.2+
- Laravel 11.x
- MySQL 8.0+ / MariaDB 10.6+ (or PostgreSQL)
- Redis (recommended for queues & caching)
- Composer 2.x

## Installation

```bash
git clone <repository-url> attendance-push-laravel
cd attendance-push-laravel

composer install

cp .env.example .env
php artisan key:generate

# Configure database & Redis in .env
php artisan migrate
php artisan db:seed          # optional demo data

php artisan storage:link
```

### Environment Variables

```env
# Device communication
PUSH_PROTOCOL_VERSION=2.4.2
PUSH_SERVER_VER=2.2.14
PUSH_ENCRYPT_ENABLED=false
PUSH_REALTIME=true
PUSH_DELAY=10
PUSH_ERROR_DELAY=30

# Timezone used for Date header synchronization
APP_TIMEZONE=Asia/Shanghai
```

## Architecture

```
app/
├── Http/Controllers/Push/
│   ├── CdataController.php          # Initialization + data upload
│   ├── GetRequestController.php     # Command polling
│   ├── DeviceCmdController.php      # Command reply
│   └── ExchangeController.php       # Encryption key/factor exchange
├── Services/Push/
│   ├── InitializationService.php
│   ├── UploadService.php            # ATTLOG, ATTPHOTO, OPERLOG, BIODATA…
│   ├── CommandService.php           # Builds & queues commands
│   ├── HybridBioService.php         # MultiBioDataSupport / MultiBioPhotoSupport
│   └── EncryptionService.php
├── Models/
│   ├── Device.php
│   ├── AttendanceLog.php
│   ├── User.php
│   ├── BioTemplate.php
│   └── DeviceCommand.php
└── Jobs/
    ├── ProcessAttendanceUpload.php
    └── DispatchDeviceCommand.php
```

### Core Flow

1. **Device boots** → `GET /iclock/cdata?SN={SN}&options=all`  
   Server returns stamps, `TransFlag`, `Realtime`, `Delay`, `MultiBioDataSupport`, etc.

2. **Device polls** → `GET /iclock/getrequest?SN={SN}` every `Delay` seconds.  
   Server replies with pending commands (`C:{CmdID}:{CmdDesc}`).

3. **Device executes command** → replies via `POST /iclock/devicecmd`.

4. **New data generated** → device uploads via `POST /iclock/cdata?table=...`.

5. **Configuration changes** → device pushes via `POST /iclock/cdata?table=options`.

## Key Protocol Endpoints

### 1. Initialization

```
GET /iclock/cdata?SN={SerialNumber}&options=all&pushver=2.4.2&DeviceType=att
```

Response body example:

```
GET OPTION FROM: {SN}
ATTLOGStamp=0
OPERLOGStamp=0
ATTPHOTOStamp=0
BIODATAStamp=0
ErrorDelay=30
Delay=10
TransTimes=00:00;14:00
TransInterval=1
TransFlag=TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP FACE UserPic WORKCODE BioPhoto
TimeZone=8
Realtime=1
Encrypt=0
ServerVer=2.2.14
PushProtVer=2.4.2
PushOptionsFlag=1
MultiBioDataSupport=0:1:1:0:0:0:0:0:0:1:0
MultiBioPhotoSupport=0:0:0:0:0:0:0:0:0:1:0
SupportPing=1
```

### 2. Upload Attendance Record

```
POST /iclock/cdata?SN={SN}&table=ATTLOG&Stamp={stamp}
```

Body (tab-separated records):

```
PIN\tTime\tStatus\tVerify\tWorkcode\tReserved\t...
```

### 3. Get Command

```
GET /iclock/getrequest?SN={SN}
```

Possible replies:

- `OK` (no pending commands)
- `C:{CmdID}:DATA UPDATE USERINFO PIN={pin}\tName=...`
- `C:{CmdID}:CLEAR LOG`
- `C:{CmdID}:REBOOT`
- `C:{CmdID}:ENROLL_BIO ...`

### 4. Command Reply

```
POST /iclock/devicecmd?SN={SN}
```

Body:

```
ID={CmdID}&Return={code}&CMD={original command}
```

## Hybrid Biometric Support

The application fully supports the **Hybrid Identification Protocol**:

- Device and server negotiate supported modalities via `MultiBioDataSupport` / `MultiBioPhotoSupport`.
- Unified template upload/issue/query/delete for:
  - Fingerprint
  - Near-infrared face
  - Visible-light face
  - Finger-vein
  - Palm (visible light)
- Comparison photo handling with optional `PostBackTmpFlag`.

## Security

- Optional RC4 / AES encryption of attendance records (protocol ≥ 2.3.0).
- Public-key + factor exchange flow for secure communication.
- Device binding via `pushcommkey`.
- All endpoints validate the device serial number (`SN`).

## Artisan Commands

```bash
# List registered devices
php artisan push:devices

# Force re-upload of a data type (sets stamp to 0)
php artisan push:reset-stamp {SN} ATTLOG

# Queue a reboot command
php artisan push:command {SN} REBOOT

# Generate a sample enrollment command
php artisan push:enroll {SN} --pin=1001 --type=face
```

## Testing

```bash
# Unit & feature tests
php artisan test

# Simulate a device initialization request
php artisan push:simulate-init {SN}

# Protocol compliance tests (uses recorded device traffic)
php artisan test --group=protocol
```

## Configuration Reference

See the official ZKTeco document:

- **Attendance PUSH Communication Protocol**  
  Software Version 2.4.2 · Doc Version 4.8 · July 2024

Important appendices used by this implementation:

- Appendix 1 – Error codes
- Appendix 3 – Operation codes
- Appendix 6 – Protocol version mapping
- Appendix 8 – Communication encryption
- Appendix 10 – Biometric type index definition

## License

This Laravel application is open-source under the MIT license.  
The underlying ZKTeco PUSH protocol specification remains the property of ZKTeco Co., Ltd.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for any new protocol feature
4. Submit a pull request

## Support

For protocol-level questions refer to the official ZKTeco documentation.  
For application issues open a GitHub issue or contact the maintainers.
