<?php
/**
 * Student Portal Helpers
 * Shared schema, access, and rendering helpers for student-facing pages
 */

require_once __DIR__ . '/parent_portal.php';

if (!function_exists('studentPortalEnsureSchema')) {
    function studentPortalEnsureSchema() {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        parentPortalEnsureSchema();

        $conn = getDbConnection();

        $ensureColumn = function (string $table, string $column, string $ddl) use ($conn) {
            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
                    error_log("Student portal schema update failed for $table.$column: " . $conn->error);
                }
            }
        };

        $roleCheck = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role' LIMIT 1");
        if ($roleCheck && ($row = $roleCheck->fetch_assoc())) {
            $columnType = strtolower((string)($row['COLUMN_TYPE'] ?? ''));
            if (strpos($columnType, 'student') === false) {
                if (!$conn->query("ALTER TABLE users MODIFY role ENUM('super_admin','admin','accountant','clerk','teacher','parent','student') NOT NULL DEFAULT 'clerk'")) {
                    error_log('Student role enum update failed: ' . $conn->error);
                }
            }
        }

        $ensureColumn('users', 'student_id', "student_id int(11) DEFAULT NULL AFTER role");
        $ensureColumn('users', 'password_encrypted', "password_encrypted text DEFAULT NULL AFTER password");
        if (function_exists('ensureStudentSchema')) {
            ensureStudentSchema();
        }

        $indexCheck = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_student_id'");
        if (!$indexCheck || $indexCheck->num_rows === 0) {
            if (!$conn->query("ALTER TABLE users ADD UNIQUE KEY idx_student_id (student_id)")) {
                error_log('Student user index update failed: ' . $conn->error);
            }
        }

        ensureDirectoryExists(STUDENT_APPLICATION_PHOTO_PATH);
        ensureDirectoryExists(STUDENT_APPLICATION_DOC_PATH);

        $conn->query(
            "CREATE TABLE IF NOT EXISTS student_applications (
                application_id int(11) NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                school_id int(11) DEFAULT NULL,
                student_name varchar(100) NOT NULL,
                father_name varchar(100) NOT NULL,
                mother_name varchar(100) DEFAULT NULL,
                gender enum('Male','Female','Other') NOT NULL DEFAULT 'Other',
                mobile varchar(15) NOT NULL,
                email varchar(100) NOT NULL,
                date_of_birth date NOT NULL,
                address text DEFAULT NULL,
                class_id int(11) NOT NULL,
                section_id int(11) DEFAULT NULL,
                profile_photo varchar(255) DEFAULT NULL,
                documents_json longtext DEFAULT NULL,
                status enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
                rejection_reason text DEFAULT NULL,
                reviewed_by int(11) DEFAULT NULL,
                reviewed_at timestamp NULL DEFAULT NULL,
                linked_student_id int(11) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (application_id),
                UNIQUE KEY idx_student_application_user (user_id),
                KEY idx_student_application_status (status),
                KEY idx_student_application_class (class_id),
                KEY idx_student_application_email (email),
                KEY idx_student_application_mobile (mobile),
                CONSTRAINT fk_student_application_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $ensureColumn('student_applications', 'school_id', "school_id int(11) DEFAULT NULL AFTER user_id");

        $conn->query(
            "UPDATE student_applications sa
             LEFT JOIN users u ON sa.user_id = u.user_id
             SET sa.school_id = COALESCE(sa.school_id, u.school_id)
             WHERE sa.school_id IS NULL AND COALESCE(u.school_id, 0) > 0"
        );

        $conn->query(
            "UPDATE student_applications sa
             LEFT JOIN students s ON sa.linked_student_id = s.student_id
             SET sa.school_id = COALESCE(sa.school_id, s.school_id)
             WHERE sa.school_id IS NULL AND COALESCE(s.school_id, 0) > 0"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS student_documents (
                document_id int(11) NOT NULL AUTO_INCREMENT,
                student_id int(11) NOT NULL,
                document_type enum('admit_card','transfer_certificate','character_certificate','marksheet') NOT NULL,
                document_title varchar(200) NOT NULL,
                exam_id int(11) DEFAULT NULL,
                issue_date date NOT NULL,
                remarks text DEFAULT NULL,
                visible_to_student tinyint(1) NOT NULL DEFAULT 0,
                payload_json longtext NOT NULL,
                document_hash char(64) NOT NULL,
                generated_by int(11) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (document_id),
                UNIQUE KEY idx_student_document_hash (document_hash),
                KEY idx_student_document_student (student_id, visible_to_student, document_type),
                KEY idx_student_document_exam (exam_id),
                KEY idx_student_document_created (created_at),
                CONSTRAINT fk_student_document_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $documentTypeCheck = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_documents' AND COLUMN_NAME = 'document_type' LIMIT 1");
        if ($documentTypeCheck && ($documentTypeRow = $documentTypeCheck->fetch_assoc())) {
            $columnType = strtolower((string)($documentTypeRow['COLUMN_TYPE'] ?? ''));
            if (strpos($columnType, 'marksheet') === false) {
                if (!$conn->query("ALTER TABLE student_documents MODIFY document_type ENUM('admit_card','transfer_certificate','character_certificate','marksheet') NOT NULL")) {
                    error_log('Student document type enum update failed: ' . $conn->error);
                }
            }
        }

        return true;
    }
}

if (!function_exists('requireStudentPortalLogin')) {
    function requireStudentPortalLogin() {
        requireLogin();

        $user = getCurrentUser();
        if (!$user || ($user['role'] ?? '') !== 'student') {
            redirect(APP_URL . '/modules/student/login.php');
        }

        if (intval($user['student_id'] ?? 0) <= 0) {
            alertAndRedirect('Your student account is not linked to a student record yet.', APP_URL . '/modules/student/login.php', 'error');
        }
    }
}

if (!function_exists('studentPortalGetCurrentStudentId')) {
    function studentPortalGetCurrentStudentId() {
        $user = getCurrentUser();
        return intval($user['student_id'] ?? 0);
    }
}

if (!function_exists('studentPortalGetStudentRecord')) {
    function studentPortalGetStudentRecord($studentId = 0) {
        $studentId = intval($studentId);
        if ($studentId <= 0) {
            return null;
        }

        $query = "SELECT s.*, c.class_name, c.class_order, sec.section_name
                  FROM students s
                  LEFT JOIN classes c ON s.class_id = c.class_id
                  LEFT JOIN sections sec ON s.section_id = sec.section_id
                  WHERE s.student_id = ?";
        $params = [$studentId];
        $types = 'i';
        if (function_exists('getCurrentSchoolId')) {
            $schoolId = intval(getCurrentSchoolId());
            if ($schoolId > 0) {
                $query .= " AND s.school_id = ?";
                $params[] = $schoolId;
                $types .= 'i';
            }
        }

        return fetchOne($query, $types, $params);
    }
}

if (!function_exists('studentPortalGetAnnouncements')) {
    function studentPortalGetAnnouncements($limit = 8) {
        return parentPortalGetAnnouncements($limit);
    }
}

if (!function_exists('studentPortalGetPaymentSettings')) {
    function studentPortalGetPaymentSettings() {
        return parentPortalGetPaymentSettings();
    }
}

if (!function_exists('studentPortalBuildUpiLink')) {
    function studentPortalBuildUpiLink($upiId, $payeeName, $amount = 0, $note = '') {
        return parentPortalBuildUpiLink($upiId, $payeeName, $amount, $note);
    }
}

if (!function_exists('studentPortalGetReceipts')) {
    function studentPortalGetReceipts($studentId, $limit = 20) {
        $studentId = intval($studentId);
        $limit = max(1, min(100, intval($limit)));
        if ($studentId <= 0) {
            return [];
        }

        return fetchAll(
            "SELECT
                fr.receipt_id, fr.receipt_no, fr.payment_date, fr.amount_paid, fr.payment_mode, fr.transaction_id,
                fr.remarks, fr.is_cancelled,
                s.student_id, s.student_name, s.admission_no, s.roll_no,
                c.class_name, sec.section_name
             FROM fee_receipts fr
             JOIN students s ON fr.student_id = s.student_id
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             WHERE fr.is_cancelled = 0
               AND fr.student_id = ?
             ORDER BY fr.payment_date DESC, fr.receipt_id DESC
             LIMIT {$limit}",
            'i',
            [$studentId]
        );
    }
}

if (!function_exists('studentPortalGetReceipt')) {
    function studentPortalGetReceipt($receiptId, $studentId) {
        $receiptId = intval($receiptId);
        $studentId = intval($studentId);
        if ($receiptId <= 0 || $studentId <= 0) {
            return null;
        }

        return fetchOne(
            "SELECT
                fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
                fr.payment_mode, fr.transaction_id, fr.payment_date, fr.remarks, fr.is_cancelled,
                s.student_name, s.admission_no, s.father_name, s.mother_name,
                s.contact_no, s.address, s.roll_no,
                c.class_name, sec.section_name
             FROM fee_receipts fr
             JOIN students s ON fr.student_id = s.student_id
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             WHERE fr.receipt_id = ? AND fr.student_id = ? AND fr.is_cancelled = 0",
            'ii',
            [$receiptId, $studentId]
        );
    }
}

if (!function_exists('studentPortalBuildDocumentPayload')) {
    function studentPortalBuildDocumentPayload(array $student, array $exam = [], array $schoolSettings = [], $issueDate = '', $remarks = '') {
        $studentSnapshot = [
            'student_id' => intval($student['student_id'] ?? 0),
            'class_id' => intval($student['class_id'] ?? 0),
            'section_id' => intval($student['section_id'] ?? 0),
            'student_name' => trim((string)($student['student_name'] ?? '')),
            'admission_no' => trim((string)($student['admission_no'] ?? '')),
            'roll_no' => trim((string)($student['roll_no'] ?? '')),
            'class_name' => trim((string)($student['class_name'] ?? '')),
            'section_name' => trim((string)($student['section_name'] ?? '')),
            'date_of_birth' => trim((string)($student['date_of_birth'] ?? '')),
            'gender' => trim((string)($student['gender'] ?? '')),
            'father_name' => trim((string)($student['father_name'] ?? '')),
            'mother_name' => trim((string)($student['mother_name'] ?? '')),
            'contact_no' => trim((string)($student['contact_no'] ?? '')),
            'email' => trim((string)($student['email'] ?? '')),
            'admission_date' => trim((string)($student['admission_date'] ?? '')),
            'status' => trim((string)($student['status'] ?? '')),
            'photo' => trim((string)($student['photo'] ?? '')),
        ];

        $examSnapshot = [];
        if (!empty($exam)) {
            $examSnapshot = [
                'exam_id' => intval($exam['exam_id'] ?? 0),
                'exam_name' => trim((string)($exam['exam_name'] ?? '')),
                'exam_type' => trim((string)($exam['exam_type'] ?? '')),
                'exam_date' => trim((string)($exam['exam_date'] ?? '')),
                'academic_year' => trim((string)($exam['academic_year'] ?? '')),
            ];
        }

        $schoolSnapshot = [
            'school_name' => trim((string)($schoolSettings['school_name'] ?? APP_NAME)),
            'school_address' => trim((string)($schoolSettings['school_address'] ?? '')),
            'school_phone' => trim((string)($schoolSettings['school_phone'] ?? '')),
            'school_email' => trim((string)($schoolSettings['school_email'] ?? '')),
            'school_logo' => trim((string)($schoolSettings['school_logo'] ?? '')),
            'banner_logo' => trim((string)($schoolSettings['banner_logo'] ?? '')),
        ];

        return [
            'student' => $studentSnapshot,
            'exam' => $examSnapshot,
            'school' => $schoolSnapshot,
            'issue_date' => trim((string)$issueDate),
            'remarks' => trim((string)$remarks),
        ];
    }
}

if (!function_exists('studentPortalSaveDocument')) {
    function studentPortalDeleteDocumentsByScope($studentId, $documentType, $examId = 0) {
        $studentId = intval($studentId);
        $documentType = trim((string)$documentType);
        $examId = intval($examId);

        if ($studentId <= 0 || $documentType === '') {
            return false;
        }

        $query = "DELETE FROM student_documents WHERE student_id = ? AND document_type = ?";
        $params = [$studentId, $documentType];
        $types = 'is';

        if ($examId > 0 && in_array($documentType, ['admit_card', 'marksheet'], true)) {
            $query .= " AND IFNULL(exam_id, 0) = ?";
            $params[] = $examId;
            $types .= 'i';
        }

        return executeQuery($query, $types, $params) !== false;
    }

    function studentPortalSaveDocument(array $documentData) {
        $studentId = intval($documentData['student_id'] ?? 0);
        $documentType = trim((string)($documentData['document_type'] ?? ''));
        $documentTitle = trim((string)($documentData['document_title'] ?? ''));
        $issueDate = trim((string)($documentData['issue_date'] ?? ''));
        $remarks = trim((string)($documentData['remarks'] ?? ''));
        $examId = intval($documentData['exam_id'] ?? 0);
        $visibleToStudent = intval($documentData['visible_to_student'] ?? 0) === 1 ? 1 : 0;
        $generatedBy = intval($documentData['generated_by'] ?? 0);
        $payloadJson = trim((string)($documentData['payload_json'] ?? ''));
        $allowedTypes = ['admit_card', 'transfer_certificate', 'character_certificate', 'marksheet'];

        if ($studentId <= 0 || $documentType === '' || $documentTitle === '' || $issueDate === '' || $payloadJson === '' || !in_array($documentType, $allowedTypes, true)) {
            return false;
        }

        if (in_array($documentType, ['admit_card', 'marksheet'], true) && $examId <= 0) {
            return false;
        }

        $hashSource = json_encode([
            'student_id' => $studentId,
            'document_type' => $documentType,
            'document_title' => $documentTitle,
            'exam_id' => $examId,
            'issue_date' => $issueDate,
            'remarks' => $remarks,
            'payload_json' => $payloadJson,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $documentHash = sha1($hashSource ?: ($studentId . '|' . $documentType . '|' . microtime(true)));

        $conn = getDbConnection();
        if (!$conn->begin_transaction()) {
            return false;
        }

        $deleteOk = studentPortalDeleteDocumentsByScope($studentId, $documentType, $examId);
        if (!$deleteOk) {
            $conn->rollback();
            return false;
        }

        $insertOk = executeQuery(
            "INSERT INTO student_documents (
                student_id, document_type, document_title, exam_id, issue_date, remarks,
                visible_to_student, payload_json, document_hash, generated_by, created_at, updated_at
            ) VALUES (?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), NOW(), NOW())",
            'isssssisss',
            [
                $studentId,
                $documentType,
                $documentTitle,
                $examId > 0 ? (string) $examId : '',
                $issueDate,
                $remarks,
                $visibleToStudent,
                $payloadJson,
                $documentHash,
                $generatedBy > 0 ? (string) $generatedBy : '',
            ]
        ) !== false;

        if (!$insertOk) {
            $conn->rollback();
            return false;
        }

        $conn->commit();
        return true;
    }
}

if (!function_exists('studentPortalDecodeDocumentPayload')) {
    function studentPortalDecodeDocumentPayload($payloadJson = '') {
        $payloadJson = trim((string)$payloadJson);
        if ($payloadJson === '') {
            return [];
        }

        $decoded = json_decode($payloadJson, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('studentPortalGetDocument')) {
    function studentPortalGetDocument($documentId) {
        $documentId = intval($documentId);
        if ($documentId <= 0) {
            return null;
        }

        $query = "SELECT sd.*, s.student_name, s.admission_no, s.roll_no, c.class_name, sec.section_name,
                    generator.full_name AS generated_by_name
             FROM student_documents sd
             JOIN students s ON sd.student_id = s.student_id
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             LEFT JOIN users generator ON sd.generated_by = generator.user_id
             WHERE sd.document_id = ?";
        $params = [$documentId];
        $types = 'i';
        if (function_exists('getCurrentSchoolId')) {
            $schoolId = intval(getCurrentSchoolId());
            if ($schoolId > 0) {
                $query .= " AND s.school_id = ?";
                $params[] = $schoolId;
                $types .= 'i';
            }
        }

        return fetchOne($query, $types, $params);
    }
}

if (!function_exists('studentPortalGetStudentDocuments')) {
    function studentPortalGetStudentDocuments($studentId, $visibleOnly = false) {
        $studentId = intval($studentId);
        if ($studentId <= 0) {
            return [];
        }

        $query = "SELECT sd.*, s.student_name, s.admission_no, s.roll_no, c.class_name, sec.section_name,
                         generator.full_name AS generated_by_name
                  FROM student_documents sd
                  JOIN students s ON sd.student_id = s.student_id
                  LEFT JOIN classes c ON s.class_id = c.class_id
                  LEFT JOIN sections sec ON s.section_id = sec.section_id
                  LEFT JOIN users generator ON sd.generated_by = generator.user_id
                  WHERE sd.student_id = ?";

        $params = [$studentId];
        $types = 'i';
        if ($visibleOnly) {
            $query .= " AND sd.visible_to_student = 1";
        }

        $query .= " ORDER BY sd.created_at DESC, sd.document_id DESC";
        return fetchAll($query, $types, $params);
    }
}

if (!function_exists('studentPortalSetDocumentVisibility')) {
    function studentPortalSetDocumentVisibility($documentId, $visibleToStudent, $updatedByUserId = 0) {
        $documentId = intval($documentId);
        $visibleToStudent = intval($visibleToStudent) === 1 ? 1 : 0;
        $updatedByUserId = intval($updatedByUserId);

        if ($documentId <= 0) {
            return false;
        }

        $result = executeQuery(
            "UPDATE student_documents SET visible_to_student = ?, updated_at = NOW() WHERE document_id = ?",
            'ii',
            [$visibleToStudent, $documentId]
        );

        if ($result !== false && $updatedByUserId > 0) {
            logActivity(
                $updatedByUserId,
                'Update Student Document',
                'student_documents',
                'Changed visibility for document ID: ' . $documentId . ' to ' . ($visibleToStudent ? 'visible' : 'hidden')
            );
        }

        return $result !== false;
    }
}

if (!function_exists('studentPortalGetActiveExams')) {
    function studentPortalGetActiveExams($limit = 25) {
        return parentPortalGetActiveExams($limit);
    }
}

if (!function_exists('studentPortalGetApplicationPhotoUrl')) {
    function studentPortalGetApplicationPhotoUrl($filename = '') {
        $filename = trim((string)$filename);
        if ($filename === '') {
            return getStudentPhotoSrc();
        }

        return APP_URL . '/assets/uploads/student_applications/photos/' . rawurlencode($filename);
    }
}

if (!function_exists('studentPortalGetApplicationDocumentUrl')) {
    function studentPortalGetApplicationDocumentUrl($filename = '') {
        $filename = trim((string)$filename);
        if ($filename === '') {
            return '';
        }

        return APP_URL . '/assets/uploads/student_applications/documents/' . rawurlencode($filename);
    }
}

if (!function_exists('studentPortalGetApplicationDocuments')) {
    function studentPortalGetApplicationDocuments($documentsJson = '') {
        $documentsJson = trim((string)$documentsJson);
        if ($documentsJson === '') {
            return [];
        }

        $decoded = json_decode($documentsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $documents = [];
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $stored = trim((string)($item['stored_name'] ?? $item['filename'] ?? ''));
                if ($stored === '') {
                    continue;
                }

                $documents[] = [
                    'stored_name' => $stored,
                    'original_name' => trim((string)($item['original_name'] ?? $stored)),
                    'url' => studentPortalGetApplicationDocumentUrl($stored),
                ];
            } elseif (is_string($item) && trim($item) !== '') {
                $stored = trim($item);
                $documents[] = [
                    'stored_name' => $stored,
                    'original_name' => $stored,
                    'url' => studentPortalGetApplicationDocumentUrl($stored),
                ];
            }
        }

        return $documents;
    }
}

if (!function_exists('studentPortalCanDeleteApplication')) {
    function studentPortalCanDeleteApplication(array $application) {
        $status = strtolower(trim((string)($application['status'] ?? '')));
        return in_array($status, ['pending', 'rejected'], true);
    }
}

if (!function_exists('studentPortalDeleteApplication')) {
    function studentPortalDeleteApplication($applicationId, $deletedByUserId = 0) {
        $applicationId = intval($applicationId);
        $deletedByUserId = intval($deletedByUserId);

        if ($applicationId <= 0) {
            return [
                'success' => false,
                'message' => 'Student application not found.',
            ];
        }

        $application = studentPortalGetApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'message' => 'Student application not found.',
            ];
        }

        if (!studentPortalCanDeleteApplication($application)) {
            return [
                'success' => false,
                'message' => 'Only pending or rejected student applications can be deleted.',
            ];
        }

        $userId = intval($application['user_id'] ?? 0);
        $status = ucfirst(strtolower((string)($application['status'] ?? 'Pending')));
        $filesToDelete = [];

        $profilePhoto = trim((string)($application['profile_photo'] ?? ''));
        if ($profilePhoto !== '') {
            $filesToDelete[] = STUDENT_APPLICATION_PHOTO_PATH . $profilePhoto;
        }

        foreach (studentPortalGetApplicationDocuments($application['documents_json'] ?? '') as $document) {
            $storedName = trim((string)($document['stored_name'] ?? ''));
            if ($storedName !== '') {
                $filesToDelete[] = STUDENT_APPLICATION_DOC_PATH . $storedName;
            }
        }

        $deleted = false;
        if ($userId > 0) {
            $deleted = softDeleteUser($userId, 'Deleted student application #' . $applicationId . ' (' . $status . ')');
        } else {
            $deleted = executeQuery(
                "DELETE FROM student_applications WHERE application_id = ?",
                'i',
                [$applicationId]
            ) !== false;
        }

        if (!$deleted) {
            return [
                'success' => false,
                'message' => 'Unable to delete the linked student login.',
            ];
        }

        foreach ($filesToDelete as $filePath) {
            deleteFile($filePath);
        }

        if ($deletedByUserId > 0) {
            logActivity(
                $deletedByUserId,
                'Delete Student Application',
                'student_applications',
                'Deleted ' . strtolower($status) . ' application ID: ' . $applicationId
            );
        }

        return [
            'success' => true,
            'message' => 'Student application deleted successfully.',
        ];
    }
}

if (!function_exists('studentPortalUploadApplicationPhoto')) {
    function studentPortalUploadApplicationPhoto($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name']) || !isset($file['error']) || intval($file['error']) !== UPLOAD_ERR_OK) {
            return false;
        }

        return uploadImage($file, STUDENT_APPLICATION_PHOTO_PATH, 450, 550);
    }
}

if (!function_exists('studentPortalUploadApplicationDocument')) {
    function studentPortalUploadApplicationDocument($file) {
        return uploadPdfDocument($file, STUDENT_APPLICATION_DOC_PATH);
    }
}

if (!function_exists('studentPortalGetApplications')) {
    function studentPortalGetApplications($status = '') {
        $status = trim((string)$status);
        $currentSchoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;

        $query = "SELECT
                    sa.*,
                    u.username,
                    u.email AS login_email,
                    u.mobile AS login_mobile,
                    u.is_active AS user_is_active,
                    u.last_login,
                    u.created_at AS user_created_at,
                    c.class_name,
                    c.class_order,
                    sec.section_name,
                    reviewer.full_name AS reviewed_by_name,
                    linked.student_name AS linked_student_name,
                    linked.admission_no AS linked_admission_no
                  FROM student_applications sa
                  LEFT JOIN users u ON sa.user_id = u.user_id
                  LEFT JOIN classes c ON sa.class_id = c.class_id
                  LEFT JOIN sections sec ON sa.section_id = sec.section_id
                  LEFT JOIN users reviewer ON sa.reviewed_by = reviewer.user_id
                  LEFT JOIN students linked ON sa.linked_student_id = linked.student_id";

        $params = [];
        $types = '';
        $conditions = [];
        if ($status !== '') {
            $conditions[] = "sa.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if ($currentSchoolId > 0) {
            $conditions[] = "COALESCE(sa.school_id, u.school_id, linked.school_id, 0) = ?";
            $params[] = $currentSchoolId;
            $types .= 'i';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY sa.created_at DESC, sa.application_id DESC";
        return empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    }
}

if (!function_exists('studentPortalGetApplication')) {
    function studentPortalGetApplication($applicationId) {
        $applicationId = intval($applicationId);
        if ($applicationId <= 0) {
            return null;
        }

        $currentSchoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
        $query = "SELECT
                sa.*,
                u.username,
                u.email AS login_email,
                u.mobile AS login_mobile,
                u.is_active AS user_is_active,
                u.last_login,
                u.created_at AS user_created_at,
                c.class_name,
                c.class_order,
                sec.section_name,
                reviewer.full_name AS reviewed_by_name,
                linked.student_name AS linked_student_name,
                linked.admission_no AS linked_admission_no
             FROM student_applications sa
             LEFT JOIN users u ON sa.user_id = u.user_id
             LEFT JOIN classes c ON sa.class_id = c.class_id
             LEFT JOIN sections sec ON sa.section_id = sec.section_id
             LEFT JOIN users reviewer ON sa.reviewed_by = reviewer.user_id
             LEFT JOIN students linked ON sa.linked_student_id = linked.student_id
             WHERE sa.application_id = ?";
        $params = [$applicationId];
        $types = 'i';

        if ($currentSchoolId > 0) {
            $query .= " AND COALESCE(sa.school_id, u.school_id, linked.school_id, 0) = ?";
            $params[] = $currentSchoolId;
            $types .= 'i';
        }

        return fetchOne($query, $types, $params);
    }
}

if (!function_exists('studentPortalSendCredentialsNotification')) {
    function studentPortalSendCredentialsNotification(array $studentUser, $plainPassword) {
        $plainPassword = trim((string) $plainPassword);
        $studentName = trim((string)($studentUser['full_name'] ?? 'Student'));
        $username = trim((string)($studentUser['username'] ?? ''));
        $mobile = trim((string)($studentUser['mobile'] ?? ''));
        $email = trim((string)($studentUser['email'] ?? ''));
        $school = getSchoolSettings();
        $schoolName = $school['school_name'] ?? APP_NAME;
        $loginUrl = APP_URL . '/modules/student/login.php';
        $message = $schoolName . ' student account approved. Login: ' . $username . '. Password: ' . $plainPassword . '. Login here: ' . $loginUrl;

        $result = [
            'sms' => false,
            'email' => false,
            'sms_message' => '',
            'email_message' => '',
            'sent' => false,
        ];

        if ($mobile !== '' && isValidMobile($mobile)) {
            $smsResult = sendSMSViaFirebase($mobile, $message);
            $result['sms'] = is_array($smsResult) && !empty($smsResult['success']);
            $result['sms_message'] = $result['sms']
                ? ($smsResult['message'] ?? 'SMS sent')
                : ($smsResult['message'] ?? 'SMS failed');
        } else {
            $result['sms_message'] = 'No valid mobile number found';
        }

        if ($email !== '' && isValidEmail($email)) {
            $subject = $schoolName . ' Student Account Approved';
            $body = "Hello {$studentName},\n\nYour student account has been approved.\n\nLogin: {$username}\nPassword: {$plainPassword}\nPortal: {$loginUrl}\n\nPlease keep these details safe.";
            $mailHost = '';
            if (!empty($_SERVER['HTTP_HOST'])) {
                $mailHost = preg_replace('/:\d+$/', '', strtolower((string)$_SERVER['HTTP_HOST']));
            } elseif (!empty($_SERVER['SERVER_NAME'])) {
                $mailHost = strtolower((string)$_SERVER['SERVER_NAME']);
            }
            $mailHost = preg_replace('/^www\./', '', $mailHost);
            if ($mailHost === '') {
                $mailHost = 'localhost';
            }
            $fromEmail = !empty($school['school_email']) && isValidEmail($school['school_email']) ? $school['school_email'] : 'no-reply@' . $mailHost;
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/plain; charset=UTF-8',
                'From: ' . $schoolName . ' <' . $fromEmail . '>',
            ];
            $result['email'] = @mail($email, $subject, $body, implode("\r\n", $headers));
            $result['email_message'] = $result['email'] ? 'Email sent' : 'Email failed';
        } else {
            $result['email_message'] = 'No valid email found';
        }

        $result['sent'] = $result['sms'] || $result['email'];
        return $result;
    }
}

if (!function_exists('studentPortalRenderLayout')) {
    function studentPortalRenderLayout($pageTitle, $contentHtml, $activeNav = 'dashboard') {
        $schoolSettings = getSchoolSettings();
        $currentUser = getCurrentUser();
        $schoolName = $schoolSettings['school_name'] ?? APP_NAME;
        $logoSrc = '';
        if (!empty($schoolSettings['banner_logo'])) {
            $logoSrc = APP_URL . '/assets/uploads/logos/' . $schoolSettings['banner_logo'];
        }

        $studentId = intval($currentUser['student_id'] ?? 0);
        $student = $studentId > 0 ? studentPortalGetStudentRecord($studentId) : null;
        $navItems = [
            'dashboard' => ['label' => 'Dashboard', 'href' => APP_URL . '/modules/student/dashboard.php', 'icon' => 'bi-house'],
            'due_fees' => ['label' => 'Due Fees', 'href' => APP_URL . '/modules/student/due_fees.php', 'icon' => 'bi-cash-stack'],
            'receipts' => ['label' => 'Receipts', 'href' => APP_URL . '/modules/student/receipts.php', 'icon' => 'bi-receipt'],
            'marksheet' => ['label' => 'Marksheet', 'href' => APP_URL . '/modules/student/marksheet.php', 'icon' => 'bi-file-earmark-pdf'],
            'admit_card' => ['label' => 'Admit Card', 'href' => APP_URL . '/modules/student/admit_card.php', 'icon' => 'bi-card-heading'],
            'certificates' => ['label' => 'Certificates', 'href' => APP_URL . '/modules/student/certificates.php', 'icon' => 'bi-award'],
        ];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo parentPortalEscape($pageTitle); ?> - <?php echo parentPortalEscape($schoolName); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
            <style><?php echo parentPortalStyles(); ?></style>
        </head>
        <body>
            <div class="parent-shell">
                <header class="parent-topbar no-print">
                    <div class="parent-topbar-inner">
                        <div class="parent-brand">
                            <?php if (!empty($logoSrc)): ?>
                                <img src="<?php echo parentPortalEscape($logoSrc); ?>" class="parent-brand-logo" alt="Logo">
                            <?php else: ?>
                                <div class="parent-brand-logo d-flex align-items-center justify-content-center text-primary fw-bold">S</div>
                            <?php endif; ?>
                            <div>
                                <div class="parent-brand-name"><?php echo parentPortalEscape($schoolName); ?></div>
                                <div class="parent-brand-subtitle">
                                    Student Portal
                                    <?php if (!empty($currentUser['full_name'])): ?>
                                        | <?php echo parentPortalEscape($currentUser['full_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($student['student_name'])): ?>
                                    <div class="parent-muted">
                                        <?php echo parentPortalEscape(($student['student_name'] ?? '') . ' | ' . ($student['admission_no'] ?? '-')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="<?php echo APP_URL; ?>/modules/auth/logout.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </div>
                    </div>
                    <nav class="parent-nav">
                        <div class="container-fluid px-0">
                            <div class="d-flex flex-wrap">
                                <?php foreach ($navItems as $navKey => $navItem): ?>
                                    <a class="nav-link<?php echo $activeNav === $navKey ? ' active' : ''; ?>" href="<?php echo parentPortalEscape($navItem['href']); ?>">
                                        <i class="bi <?php echo parentPortalEscape($navItem['icon']); ?>"></i>
                                        <?php echo parentPortalEscape($navItem['label']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </nav>
                </header>

                <main class="parent-main">
                    <?php echo $contentHtml; ?>
                </main>
            </div>
            <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
