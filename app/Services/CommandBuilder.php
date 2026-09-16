<?php

namespace App\Services;

use Random\RandomException;

class CommandBuilder
{
    /**
     * @throws RandomException
     */
    public static function generateCmdId(): string
    {
        return substr(uniqid('', false) . bin2hex(random_bytes(2)), 0, 16);
    }

    /**
     * @throws RandomException
     */
    public static function generateCommand(string $commandText): string
    {
        return 'C:' . self::generateCmdId() . ':' . $commandText;
    }

    /**
     * @throws RandomException
     */
    public static function build(string $commandText): string
    {
        return self::generateCommand($commandText);
    }

    public static function extractCmdId(string $fullCommand): ?string
    {
        $fullCommand = trim($fullCommand);

        if (preg_match('/^C:([A-Za-z0-9]+):/', $fullCommand, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function extractCommandBody(string $fullCommand): ?string
    {
        $fullCommand = trim($fullCommand);

        if (preg_match('/^C:[A-Za-z0-9]+:(.*)$/s', $fullCommand, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function buildFields(array $map, array $data): string
    {
        $fields = [];
        foreach ($map as $key => $label) {
            if (array_key_exists($key, $data) && $data[$key] !== '' && $data[$key] !== null) {
                $fields[] = "{$label}={$data[$key]}";
            }
        }
        return implode("\t", $fields);
    }

    // ==================== DATA UPDATE ====================

    public static function updateUser(array $data): string
    {
        $map = [
            'pin' => 'PIN',
            'name' => 'Name',
            'pri' => 'Pri',
            'passwd' => 'Passwd',
            'card' => 'Card',
            'grp' => 'Grp',
            'tz' => 'TZ',
            'verify' => 'Verify',
            'vicecard' => 'ViceCard',
        ];
        return 'DATA UPDATE USERINFO ' . self::buildFields($map, $data);
    }

    public static function updateIdCard(array $data): string
    {
        $fields = [];
        foreach ($data as $key => $val) {
            if ($val !== '' && $val !== null) {
                $fields[] = strtoupper($key) . "={$val}";
            }
        }
        return 'DATA UPDATE IDCARD ' . implode("\t", $fields);
    }

    public static function updateFingerprint(string $pin, int $fid, string $tmpBase64, int $valid = 1): string
    {
        $size = strlen($tmpBase64);
        return "DATA UPDATE FINGERTMP PIN={$pin}\tFID={$fid}\tSize={$size}\tValid={$valid}\tTMP={$tmpBase64}";
    }

    public static function updateFace(string $pin, int $fid, string $tmpBase64, int $valid = 1): string
    {
        $size = strlen($tmpBase64);
        return "DATA UPDATE FACE PIN={$pin}\tFID={$fid}\tSize={$size}\tValid={$valid}\tTMP={$tmpBase64}";
    }

    public static function updateFingerVein(string $pin, int $fid, int $index, string $tmpBase64, int $valid = 1): string
    {
        $size = strlen($tmpBase64);
        return "DATA UPDATE FVEIN Pin={$pin}\tFID={$fid}\tIndex={$index}\tSize={$size}\tValid={$valid}\tTmp={$tmpBase64}";
    }

    public static function updateBioData(array $data): string
    {
        $map = [
            'pin' => 'Pin',
            'no' => 'No',
            'index' => 'Index',
            'valid' => 'Valid',
            'duress' => 'Duress',
            'type' => 'Type',
            'majorver' => 'MajorVer',
            'minorver' => 'MinorVer',
            'format' => 'Format',
            'tmp' => 'Tmp',
        ];
        return 'DATA UPDATE BIODATA ' . self::buildFields($map, $data);
    }

    public static function updateUserPhoto(string $pin, string $contentBase64, ?string $filename = null): string
    {
        $size = strlen($contentBase64);
        $parts = "PIN={$pin}\tSize={$size}\tContent={$contentBase64}";
        if ($filename) {
            $parts .= "\tFileName={$filename}";
        }
        return 'DATA UPDATE USERPIC ' . $parts;
    }

    public static function updateBioPhoto(
        string  $pin,
        int     $type,
        string  $contentBase64 = '',
        ?string $url = null,
        int     $format = 0,
        ?int    $postBackTmpFlag = null
    ): string
    {
        $size = strlen($contentBase64);
        $parts = "PIN={$pin}\tType={$type}\tSize={$size}";
        if ($contentBase64) {
            $parts .= "\tContent={$contentBase64}";
        }
        if ($url) {
            $parts .= "\tUrl={$url}";
        }
        $parts .= "\tFormat={$format}";                    // ✅ Always add Format
        if ($postBackTmpFlag !== null) {
            $parts .= "\tPostBackTmpFlag={$postBackTmpFlag}";
        }
        return 'DATA UPDATE BIOPHOTO ' . $parts;
    }

    public static function updateSms(string $msg, int $tag = 253, int $uid = 0, int $min = 0, string $startTime = ''): string
    {
        return "DATA UPDATE SMS MSG={$msg}\tTAG={$tag}\tUID={$uid}\tMIN={$min}\tStartTime={$startTime}";
    }

    public static function updateUserSms(string $pin, int $uid): string
    {
        return "DATA UPDATE USER_SMS PIN={$pin}\tUID={$uid}";
    }

    public static function updateAdPic(int $index, string $contentBase64, string $extension = 'jpg'): string
    {
        $size = strlen($contentBase64);
        return "DATA UPDATE ADPIC Index={$index}\tSize={$size}\tExtension={$extension}\tContent={$contentBase64}";
    }

    public static function updateWorkCode(string $pin, string $code, string $name = ''): string
    {
        return "DATA UPDATE WORKCODE PIN={$pin}\tCODE={$code}\tNAME={$name}";
    }

    public static function updateShortcutKey(array $data): string
    {
        $fields = [];
        foreach ($data as $key => $val) {
            if ($val !== '' && $val !== null) {
                $fields[] = ucfirst($key) . "={$val}";
            }
        }
        return 'DATA UPDATE ShortcutKey ' . implode("\t", $fields);
    }

    public static function updateAccessGroup(int $id, int $verify = 0, int $validHoliday = 0, string $tz = ''): string
    {
        return "DATA UPDATE AccGroup ID={$id}\tVerify={$verify}\tValidHoliday={$validHoliday}\tTZ={$tz}";
    }

    public static function updateAccessTimeZone(array $data): string
    {
        $fields = [];
        foreach ($data as $key => $val) {
            if ($val !== '' && $val !== null) {
                $fields[] = ucfirst($key) . "={$val}";
            }
        }
        return 'DATA UPDATE AccTimeZone ' . implode("\t", $fields);
    }

    public static function updateAccessHoliday(int $uid, string $holidayName, string $startDate, string $endDate, int $timeZone = 0): string
    {
        return "DATA UPDATE AccHoliday UID={$uid}\tHolidayName={$holidayName}\tStartDate={$startDate}\tEndDate={$endDate}\tTimeZone={$timeZone}";
    }

    public static function updateAccessUnlockComb(int $uid, array $groups): string
    {
        $parts = "UID={$uid}";
        for ($i = 1; $i <= 5; $i++) {
            if (isset($groups[$i])) {
                $parts .= "\tGroup{$i}={$groups[$i]}";
            }
        }
        return 'DATA UPDATE AccUnLockComb ' . $parts;
    }

    public static function updateBlacklist(string $idNumber): string
    {
        return "DATA UPDATE Blacklist IDNum={$idNumber}";
    }

    // ==================== DATA DELETE ====================

    public static function deleteUser(string $pin): string
    {
        return "DATA DELETE USERINFO PIN={$pin}";
    }

    public static function deleteFingerprint(string $pin, ?int $fid = null): string
    {
        $cmd = "DATA DELETE FINGERTMP PIN={$pin}";
        if ($fid !== null) {
            $cmd .= "\tFID={$fid}";
        }
        return $cmd;
    }

    public static function deleteFace(string $pin): string
    {
        return "DATA DELETE FACE PIN={$pin}";
    }

    public static function deleteFingerVein(string $pin, ?int $fid = null): string
    {
        $cmd = "DATA DELETE FVEIN PIN={$pin}";
        if ($fid !== null) {
            $cmd .= "\tFID={$fid}";
        }
        return $cmd;
    }

    public static function deleteBioData(string $pin, ?int $type = null, ?int $no = null): string
    {
        $cmd = "DATA DELETE BIODATA PIN={$pin}";
        if ($type !== null) {
            $cmd .= "\tType={$type}";
            if ($no !== null) {
                $cmd .= "\tNo={$no}";
            }
        }
        return $cmd;
    }

    public static function deleteUserPhoto(string $pin): string
    {
        return "DATA DELETE USERPIC PIN={$pin}";
    }

    public static function deleteBioPhoto(string $pin): string
    {
        return "DATA DELETE BIOPHOTO PIN={$pin}";
    }

    public static function deleteSms(int $uid): string
    {
        return "DATA DELETE SMS UID={$uid}";
    }

    public static function deleteWorkCode(string $code): string
    {
        return "DATA DELETE WORKCODE CODE={$code}";
    }

    public static function deleteAdPic(int $index): string
    {
        return "DATA DELETE ADPIC Index={$index}";
    }

    // ==================== DATA QUERY ====================

    public static function queryAttLog(string $startTime, string $endTime): string
    {
        return "DATA QUERY ATTLOG StartTime={$startTime}\tEndTime={$endTime}";
    }

    public static function queryAttPhoto(string $startTime, string $endTime): string
    {
        return "DATA QUERY ATTPHOTO StartTime={$startTime}\tEndTime={$endTime}";
    }

    public static function queryUserInfo(?string $pin = null): string
    {
        $cmd = 'DATA QUERY USERINFO';
        if (filled($pin)) {
            $cmd .= " PIN={$pin}";
        }

        return $cmd;
    }

    /** Alias used by device:user query */
    public static function queryUser(?string $pin = null): string
    {
        return self::queryUserInfo($pin);
    }

    public static function queryFingerprint(string $pin, ?int $fingerId = null): string
    {
        $cmd = "DATA QUERY FINGERTMP PIN={$pin}";
        if ($fingerId !== null) {
            $cmd .= "\tFingerID={$fingerId}";
        }
        return $cmd;
    }

    public static function queryBioData(int $type, ?string $pin = null, ?int $no = null): string
    {
        $cmd = "DATA QUERY BIODATA Type={$type}";
        if ($pin !== null) {
            $cmd .= "\tPIN={$pin}";
            if ($no !== null) {
                $cmd .= "\tNo={$no}";
            }
        }
        return $cmd;
    }
    /**
     * Set device date & time.
     * Format required by most firmwares: YYYY-MM-DD HH:MM:SS
     */
    public static function setDateTime(?\DateTimeInterface $dateTime = null): string
    {
        $dt = $dateTime
            ? \Carbon\Carbon::instance($dateTime)
            : now();

        // Most firmwares accept this
        return self::setOption('TimeZone', '4');
    }
    // ==================== CLEAR ====================

    public static function clearLog(): string
    {
        return 'CLEAR LOG';
    }

    public static function clearPhoto(): string
    {
        return 'CLEAR PHOTO';
    }

    public static function clearAllData(): string
    {
        return 'CLEAR DATA';
    }

    public static function clearBioData(): string
    {
        return 'CLEAR BIODATA';
    }

    // ==================== CHECK ====================

    public static function checkUpdate(): string
    {
        return 'CHECK';
    }

    public static function checkAndTransmit(): string
    {
        return 'LOG';
    }

    public static function verifySum(string $startTime, string $endTime): string
    {
        return "VERIFY SUM ATTLOG StartTime={$startTime}\tEndTime={$endTime}";
    }

    // ==================== CONFIG ====================

    public static function setOption(string $key, string $value): string
    {
        return "SET OPTION {$key}={$value}";
    }

    public static function reloadOptions(): string
    {
        return 'RELOAD OPTIONS';
    }

    public static function info(): string
    {
        return 'INFO';
    }

// ==================== FILE ====================

    public static function getFile(string $filePath): string
    {
        return "GetFile {$filePath}";
    }

    /**
     * Put a file onto the device.
     *
     * Protocol: PutFile {url}\t{filePath}[\tAction=...][\tTableName=...\tRecordCount=...]
     */
    public static function putFile(
        string  $url,
        string  $filePath,
        ?string $action = null,
        ?string $tableName = null,
        ?int    $recordCount = null
    ): string {
        $cmd = "PutFile {$url}\t{$filePath}";

        if ($action) {
            $cmd .= "\tAction={$action}";

            if ($action === 'SyncData' && $tableName !== null && $recordCount !== null) {
                $cmd .= "\tTableName={$tableName}\tRecordCount={$recordCount}";
            }
        }

        return $cmd;
    }

    /**
     * Delete a file on the device.
     * Most firmwares do not have a native delete-file command,
     * so we use SHELL as the most reliable method.
     */
    public static function deleteFile(string $filePath): string
    {
        return self::shell('rm -f ' . escapeshellarg($filePath));
    }

    // ==================== ENROLL ====================

    public static function enrollFp(string $pin, int $fid, int $retry = 3, int $overwrite = 0): string
    {
        return "ENROLL_FP PIN={$pin}\tFID={$fid}\tRETRY={$retry}\tOVERWRITE={$overwrite}";
    }

    public static function enrollMf(string $pin, int $retry = 3): string
    {
        return "ENROLL_MF PIN={$pin}\tRETRY={$retry}";
    }

    public static function enrollBio(int $type, string $pin, string $cardNo = '', int $retry = 3, int $overwrite = 0): string
    {
        $cmd = "ENROLL_BIO TYPE={$type}\tPIN={$pin}";
        if ($cardNo !== '') {
            $cmd .= "\tCardNo={$cardNo}";
        }
        $cmd .= "\tRETRY={$retry}\tOVERWRITE={$overwrite}";
        return $cmd;
    }

    // ==================== CONTROL ====================

    public static function reboot(): string
    {
        return 'REBOOT';
    }

    public static function unlockDoor(): string
    {
        return 'AC_UNLOCK';
    }

    public static function cancelAlarm(): string
    {
        return 'AC_UNALARM';
    }

    // ==================== SYSTEM ====================

    public static function shell(string $systemCmd): string
    {
        return "SHELL {$systemCmd}";
    }

    public static function upgrade(string $checksum, string $url, int $size, int $type = 1): string
    {
        return "UPGRADE type={$type},checksum={$checksum},url={$url},size={$size}";
    }

    public static function combine(array $commands): string
    {
        return implode("\n", $commands);
    }


}
