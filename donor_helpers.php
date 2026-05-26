<?php
declare(strict_types=1);

/** Application timezone for donation dates and “today” checks (override with env BDMS_TZ). */
function bdms_app_timezone(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        $name = getenv('BDMS_TZ');
        $tz = new DateTimeZone(is_string($name) && $name !== '' ? $name : 'Asia/Manila');
    }

    return $tz;
}

function bdms_today_ymd(): string
{
    return (new DateTimeImmutable('now', bdms_app_timezone()))->format('Y-m-d');
}

function donor_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function donor_phone_key(string $contact): string
{
    $digits = preg_replace('/\D+/', '', $contact);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) >= 12 && strncmp($digits, '63', 2) === 0) {
        return substr($digits, -10);
    }
    if (strlen($digits) >= 11 && $digits[0] === '0') {
        return substr($digits, -10);
    }
    if (strlen($digits) >= 10) {
        return substr($digits, -10);
    }
    return $digits;
}

/**
 * @param array<string, mixed> $p
 * @return array{ok: bool, errors: string[]}
 */
function donor_validate_registration_inputs(array $p): array
{
    $errors = [];

    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Full name is too long.';
    }

    $birthRaw = trim((string) ($p['birthdate'] ?? ''));
    if ($birthRaw === '') {
        $errors[] = 'Birth date is required.';
    } else {
        $birth = DateTimeImmutable::createFromFormat('Y-m-d', $birthRaw);
        if ($birth === false) {
            $errors[] = 'Birth date is invalid.';
        } else {
            $today = new DateTimeImmutable('today');
            if ($birth > $today) {
                $errors[] = 'Birth date cannot be in the future.';
            } else {
                $age = $birth->diff($today)->y;
                if ($age < 16) {
                    $errors[] = 'Donor must be at least 16 years old.';
                }
                if ($age > 100) {
                    $errors[] = 'Please verify the birth date.';
                }
            }
        }
    }

    $email = trim((string) ($p['email'] ?? ''));
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email format is invalid.';
    } elseif (mb_strlen($email) > 255) {
        $errors[] = 'Email is too long.';
    }

    $contact = (string) ($p['contact_number'] ?? '');
    $phoneKey = donor_phone_key($contact);
    if ($phoneKey === '' || strlen($phoneKey) < 10) {
        $errors[] = 'Contact number must include at least 10 digits.';
    }

    $blood = (string) ($p['blood_type'] ?? '');
    $allowedBlood = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($blood, $allowedBlood, true)) {
        $errors[] = 'Blood type is invalid.';
    }

    $gender = (string) ($p['gender'] ?? '');
    $allowedGender = ['Male', 'Female', 'Other'];
    if (!in_array($gender, $allowedGender, true)) {
        $errors[] = 'Gender is invalid.';
    }

    $classification = (string) ($p['classification'] ?? '');
    $allowedClass = ['Student', 'Staff', 'Public'];
    if (!in_array($classification, $allowedClass, true)) {
        $errors[] = 'Classification is invalid.';
    }

    $civil = (string) ($p['civil_status'] ?? '');
    $allowedCivil = ['Single', 'Married', 'Widowed'];
    if (!in_array($civil, $allowedCivil, true)) {
        $errors[] = 'Civil status is invalid.';
    }

    $hist = (string) ($p['donation_history'] ?? '');
    $allowedHist = ['First Time', 'Regular Donor', 'Occasional Donor'];
    if (!in_array($hist, $allowedHist, true)) {
        $errors[] = 'Donation history selection is invalid.';
    }

    $address = trim((string) ($p['address'] ?? ''));
    if ($address === '') {
        $errors[] = 'Address is required.';
    }

    $bq = $p['blood_quantity'] ?? '';
    if ($bq === '' || !is_numeric($bq) || (int) $bq <= 0) {
        $errors[] = 'Blood quantity must be a positive number.';
    }

    $collRaw = trim((string) ($p['collection_date'] ?? ''));
    if ($collRaw === '') {
        $errors[] = 'Collection date is required.';
    } else {
        $coll = DateTimeImmutable::createFromFormat('Y-m-d', $collRaw);
        if ($coll === false) {
            $errors[] = 'Collection date is invalid.';
        }
    }

    $dtype = (string) ($p['donation_type'] ?? '');
    $allowedType = ['In House', 'Walk-In/Voluntary', 'Replacement', 'Patient-Directed'];
    if (!in_array($dtype, $allowedType, true)) {
        $errors[] = 'Donation type is invalid.';
    }

    $dloc = (string) ($p['donation_location'] ?? '');
    $allowedLoc = ['Red Cross Area', 'School'];
    if (!in_array($dloc, $allowedLoc, true)) {
        $errors[] = 'Donation location is invalid.';
    }

    return ['ok' => $errors === [], 'errors' => $errors];
}

/**
 * @param array<string, mixed> $p
 * @return array{ok: bool, errors: string[]}
 */
function donor_validate_donation_inputs(array $p): array
{
    $errors = [];

    $donorId = $p['donor_id'] ?? '';
    if ($donorId === '' || !is_numeric($donorId) || (int) $donorId <= 0) {
        $errors[] = 'Donor is required.';
    }

    $blood = trim((string) ($p['blood_type'] ?? ''));
    $allowedBlood = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($blood, $allowedBlood, true)) {
        $errors[] = 'Blood type is invalid.';
    }

    $qty = $p['quantity_ml'] ?? '';
    if ($qty === '' || !is_numeric($qty) || (int) $qty <= 0) {
        $errors[] = 'Quantity must be a positive number.';
    } elseif ((int) $qty > 600) {
        $errors[] = 'Quantity seems unreasonably high; please verify.';
    }

    $dateRaw = trim((string) ($p['donation_date'] ?? ''));
    if ($dateRaw === '') {
        $errors[] = 'Donation date is required.';
    } else {
        $tz = bdms_app_timezone();
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw, $tz);
        if ($d === false) {
            $errors[] = 'Donation date is invalid.';
        } else {
            $d = $d->setTime(0, 0, 0);
            $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
            if ($d > $today) {
                $errors[] = 'Donation date cannot be in the future.';
            }
        }
    }

    $elig = (string) ($p['eligibility_status'] ?? '');
    $allowedElig = ['Eligible', 'Temporarily Deferred', 'Not Eligible'];
    if (!in_array($elig, $allowedElig, true)) {
        $errors[] = 'Eligibility status is invalid.';
    }

    return ['ok' => $errors === [], 'errors' => $errors];
}

function donor_find_duplicate_id(mysqli $conn, string $email, string $contact, string $birthdateYmd): ?int
{
    $emailNorm = donor_normalize_email($email);
    $phoneKey = donor_phone_key($contact);

    $sql = 'SELECT id, contact_number, email, birthdate FROM donors
            WHERE LOWER(TRIM(email)) = ? OR birthdate = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('ss', $emailNorm, $birthdateYmd);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rowEmail = donor_normalize_email((string) ($row['email'] ?? ''));
        if ($rowEmail === $emailNorm) {
            $id = (int) $row['id'];
            $stmt->close();
            return $id;
        }
        $rowBirth = (string) ($row['birthdate'] ?? '');
        if ($rowBirth === $birthdateYmd && donor_phone_key((string) ($row['contact_number'] ?? '')) === $phoneKey) {
            $id = (int) $row['id'];
            $stmt->close();
            return $id;
        }
    }
    $stmt->close();
    return null;
}
