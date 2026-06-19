<?php
/**
 * Exam Routine Manager
 * Add subject-wise exam date and time for class-wise admit cards.
 */

require_once '../../config/config.php';
require_once '../../includes/admit_card_renderer.php';

requireLogin();
requirePermission('marks', 'add');

$pageTitle = 'Exam Routine';
$currentUser = getCurrentUser();
$message = '';
$error = '';

admitCardEnsureRoutineSchema();

function examRoutineNormalizeDate($value, $fallback = '') {
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('Y-m-d', $timestamp);
}

function examRoutineNormalizeTime($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('H:i:s', $timestamp);
}

function examRoutineLoadSubjects($classId, $selectedSubjectId = 0) {
    $classId = intval($classId);
    $selectedSubjectId = intval($selectedSubjectId);
    $subjectsTableExists = count(fetchAll("SHOW TABLES LIKE 'subjects'")) > 0;
    if (!$subjectsTableExists) {
        return [];
    }

    $classSubjectTableExists = count(fetchAll("SHOW TABLES LIKE 'class_subjects'")) > 0;

    if ($classId > 0 && $classSubjectTableExists) {
        $subjects = fetchAll(
            "SELECT DISTINCT sub.subject_id, sub.subject_name, sub.subject_code
             FROM class_subjects cs
             INNER JOIN subjects sub ON sub.subject_id = cs.subject_id
             WHERE cs.class_id = ? AND sub.is_active = 1
             ORDER BY sub.subject_name ASC",
            'i',
            [$classId]
        );

        if (!empty($subjects) || $selectedSubjectId <= 0) {
            return $subjects;
        }
    }

    $subjects = fetchAll(
        "SELECT subject_id, subject_name, subject_code
         FROM subjects
         WHERE is_active = 1
         ORDER BY subject_name ASC"
    );

    if ($selectedSubjectId > 0) {
        $found = false;
        foreach ($subjects as $subject) {
            if (intval($subject['subject_id'] ?? 0) === $selectedSubjectId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $selectedSubject = fetchOne(
                "SELECT subject_id, subject_name, subject_code FROM subjects WHERE subject_id = ?",
                'i',
                [$selectedSubjectId]
            );
            if ($selectedSubject) {
                $subjects[] = $selectedSubject;
            }
        }
    }

    return $subjects;
}

function examRoutineBuildClassLabel(array $row) {
    $className = trim((string)($row['class_name'] ?? ''));
    $sectionName = trim((string)($row['section_name'] ?? ''));

    if ($className === '' && $sectionName === '') {
        return '-';
    }

    if ($className !== '' && $sectionName !== '') {
        return $className . ' / ' . $sectionName;
    }

    return $className !== '' ? $className : $sectionName;
}

$examsTableExists = count(fetchAll("SHOW TABLES LIKE 'exams'")) > 0;
$subjectsTableExists = count(fetchAll("SHOW TABLES LIKE 'subjects'")) > 0;
$classesTableExists = count(fetchAll("SHOW TABLES LIKE 'classes'")) > 0;
$sectionsTableExists = count(fetchAll("SHOW TABLES LIKE 'sections'")) > 0;
$classSubjectTableExists = count(fetchAll("SHOW TABLES LIKE 'class_subjects'")) > 0;

$classes = $classesTableExists
    ? fetchAll("SELECT class_id, class_name, class_order FROM classes WHERE is_active = 1 ORDER BY class_order, class_name")
    : [];
$sections = $sectionsTableExists
    ? fetchAll("SELECT section_id, section_name FROM sections WHERE is_active = 1 ORDER BY section_name")
    : [];
$exams = $examsTableExists
    ? fetchAll("SELECT exam_id, exam_name, exam_type, exam_date, academic_year FROM exams WHERE is_active = 1 ORDER BY exam_date DESC, exam_id DESC")
    : [];

$selectedRoutineId = intval($_GET['edit'] ?? $_POST['routine_id'] ?? 0);
$selectedExamId = intval($_POST['exam_id'] ?? $_GET['exam_id'] ?? 0);
$selectedClassId = intval($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
$selectedSectionId = intval($_POST['section_id'] ?? $_GET['section_id'] ?? 0);
$selectedSubjectId = intval($_POST['subject_id'] ?? $_GET['subject_id'] ?? 0);

$editRoutine = null;
if ($selectedRoutineId > 0) {
    $editRoutine = fetchOne("SELECT * FROM exam_routines WHERE routine_id = ?", 'i', [$selectedRoutineId]);
    if ($editRoutine) {
        $selectedExamId = intval($editRoutine['exam_id'] ?? $selectedExamId);
        $selectedClassId = intval($editRoutine['class_id'] ?? $selectedClassId);
        $selectedSectionId = intval($editRoutine['section_id'] ?? $selectedSectionId);
        $selectedSubjectId = intval($editRoutine['subject_id'] ?? $selectedSubjectId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = '';
    if (isset($_POST['add_routine'])) {
        $action = 'add';
    } elseif (isset($_POST['update_routine'])) {
        $action = 'update';
    } elseif (isset($_POST['delete_routine'])) {
        $action = 'delete';
    }

    $selectedExamId = intval($_POST['exam_id'] ?? 0);
    $selectedClassId = intval($_POST['class_id'] ?? 0);
    $selectedSectionId = intval($_POST['section_id'] ?? 0);
    $selectedSubjectId = intval($_POST['subject_id'] ?? 0);

    if ($action === 'delete') {
        $deleteId = intval($_POST['routine_id'] ?? 0);
        if ($deleteId <= 0) {
            $error = 'Invalid routine selected for deletion.';
        } else {
            $deleted = executeQuery("DELETE FROM exam_routines WHERE routine_id = ?", 'i', [$deleteId]);
            if ($deleted) {
                $message = 'Routine deleted successfully.';
                logActivity($currentUser['user_id'], 'Delete Exam Routine', 'marks', 'Deleted routine ID: ' . $deleteId);
                if ($selectedRoutineId === $deleteId) {
                    $editRoutine = null;
                }
            } else {
                $error = 'Failed to delete routine.';
            }
        }
    } else {
        $routineId = intval($_POST['routine_id'] ?? 0);
        $examDate = examRoutineNormalizeDate($_POST['exam_date'] ?? '', '');
        $startTime = examRoutineNormalizeTime($_POST['start_time'] ?? '');
        $endTime = examRoutineNormalizeTime($_POST['end_time'] ?? '');
        $roomNo = sanitize($_POST['room_no'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($selectedExamId <= 0 || $selectedClassId <= 0 || $selectedSubjectId <= 0 || $examDate === '' || $startTime === '' || $endTime === '') {
            $error = 'Exam, class, subject, date, start time, and end time are required.';
        } elseif (strtotime($startTime) !== false && strtotime($endTime) !== false && strtotime($startTime) >= strtotime($endTime)) {
            $error = 'End time must be later than start time.';
        } else {
            $duplicateCheckQuery = "SELECT routine_id
                                    FROM exam_routines
                                    WHERE exam_id = ? AND class_id = ? AND section_id = ? AND subject_id = ?";
            $duplicateParams = [$selectedExamId, $selectedClassId, $selectedSectionId, $selectedSubjectId];
            $duplicateTypes = 'iiii';
            if ($routineId > 0) {
                $duplicateCheckQuery .= " AND routine_id != ?";
                $duplicateParams[] = $routineId;
                $duplicateTypes .= 'i';
            }

            $duplicate = fetchOne($duplicateCheckQuery, $duplicateTypes, $duplicateParams);
            if ($duplicate) {
                $error = 'This subject already has a routine for the selected exam, class, and section.';
            } else {
                if ($action === 'add') {
                    $result = executeQuery(
                        "INSERT INTO exam_routines (
                            exam_id, class_id, section_id, subject_id, exam_date, start_time, end_time,
                            room_no, notes, display_order, is_active, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?)",
                        'iiiisssssiii',
                        [
                            $selectedExamId,
                            $selectedClassId,
                            $selectedSectionId,
                            $selectedSubjectId,
                            $examDate,
                            $startTime,
                            $endTime,
                            $roomNo,
                            $notes,
                            $displayOrder,
                            $isActive,
                            intval($currentUser['user_id'] ?? 0),
                        ]
                    );

                    if ($result) {
                        $message = 'Routine added successfully.';
                        logActivity($currentUser['user_id'], 'Add Exam Routine', 'marks', 'Added routine for exam ID: ' . $selectedExamId);
                    } else {
                        $error = 'Failed to add routine.';
                    }
                } elseif ($action === 'update') {
                    $routineId = intval($_POST['routine_id'] ?? 0);
                    if ($routineId <= 0) {
                        $error = 'Invalid routine selected for update.';
                    } else {
                        $result = executeQuery(
                            "UPDATE exam_routines
                             SET exam_id = ?, class_id = ?, section_id = ?, subject_id = ?, exam_date = ?,
                                 start_time = ?, end_time = ?, room_no = NULLIF(?, ''), notes = NULLIF(?, ''),
                                 display_order = ?, is_active = ?
                             WHERE routine_id = ?",
                            'iiiisssssiii',
                            [
                                $selectedExamId,
                                $selectedClassId,
                                $selectedSectionId,
                                $selectedSubjectId,
                                $examDate,
                                $startTime,
                                $endTime,
                                $roomNo,
                                $notes,
                                $displayOrder,
                                $isActive,
                                $routineId,
                            ]
                        );

                        if ($result) {
                            $message = 'Routine updated successfully.';
                            logActivity($currentUser['user_id'], 'Update Exam Routine', 'marks', 'Updated routine ID: ' . $routineId);
                            $editRoutine = fetchOne("SELECT * FROM exam_routines WHERE routine_id = ?", 'i', [$routineId]);
                        } else {
                            $error = 'Failed to update routine.';
                        }
                    }
                }
            }
        }
    }
}

$subjectRows = examRoutineLoadSubjects($selectedClassId, $selectedSubjectId);
$routineRows = [];
if ($subjectsTableExists && $examsTableExists && $classesTableExists && ($selectedExamId > 0 || $selectedClassId > 0)) {
    $routineQuery = "SELECT er.*, e.exam_name, e.exam_type, e.exam_date AS exam_base_date,
                            c.class_name, c.class_order, sec.section_name,
                            sub.subject_name, sub.subject_code,
                            u.full_name AS created_by_name
                     FROM exam_routines er
                     INNER JOIN exams e ON e.exam_id = er.exam_id
                     INNER JOIN classes c ON c.class_id = er.class_id
                     LEFT JOIN sections sec ON sec.section_id = er.section_id
                     INNER JOIN subjects sub ON sub.subject_id = er.subject_id
                     LEFT JOIN users u ON u.user_id = er.created_by
                     WHERE 1 = 1";
    $routineParams = [];
    $routineTypes = '';

    if ($selectedExamId > 0) {
        $routineQuery .= " AND er.exam_id = ?";
        $routineParams[] = $selectedExamId;
        $routineTypes .= 'i';
    }

    if ($selectedClassId > 0) {
        $routineQuery .= " AND er.class_id = ?";
        $routineParams[] = $selectedClassId;
        $routineTypes .= 'i';
    }

    if ($selectedSectionId > 0) {
        $routineQuery .= " AND er.section_id IN (0, ?)";
        $routineParams[] = $selectedSectionId;
        $routineTypes .= 'i';
    }

    $routineQuery .= " ORDER BY e.exam_date DESC, c.class_order ASC, c.class_name ASC,
                              er.section_id ASC, er.display_order ASC, er.exam_date ASC,
                              er.start_time ASC, sub.subject_name ASC, er.routine_id DESC";
    $routineRows = fetchAll($routineQuery, $routineTypes, $routineParams);
}

if (!$subjectsTableExists || !$examsTableExists || !$classesTableExists) {
    if (!$subjectsTableExists) {
        $error = 'Subjects table is missing. Please create the marks tables first.';
    } elseif (!$examsTableExists) {
        $error = 'Exams table is missing. Please create the marks tables first.';
    } elseif (!$classesTableExists) {
        $error = 'Classes table is missing. Please create the school tables first.';
    }
}

$formValues = [
    'exam_date' => $editRoutine['exam_date'] ?? '',
    'start_time' => substr((string)($editRoutine['start_time'] ?? ''), 0, 5),
    'end_time' => substr((string)($editRoutine['end_time'] ?? ''), 0, 5),
    'room_no' => $editRoutine['room_no'] ?? '',
    'notes' => $editRoutine['notes'] ?? '',
    'display_order' => $editRoutine['display_order'] ?? 0,
    'is_active' => !$editRoutine || intval($editRoutine['is_active'] ?? 1) === 1 ? 1 : 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['add_routine'] ?? $_POST['update_routine'] ?? false)) {
    $formValues['exam_date'] = examRoutineNormalizeDate($_POST['exam_date'] ?? '', $formValues['exam_date']);
    $formValues['start_time'] = substr(examRoutineNormalizeTime($_POST['start_time'] ?? ''), 0, 5);
    $formValues['end_time'] = substr(examRoutineNormalizeTime($_POST['end_time'] ?? ''), 0, 5);
    $formValues['room_no'] = trim((string)($_POST['room_no'] ?? ''));
    $formValues['notes'] = trim((string)($_POST['notes'] ?? ''));
    $formValues['display_order'] = intval($_POST['display_order'] ?? $formValues['display_order']);
    $formValues['is_active'] = isset($_POST['is_active']) ? 1 : 0;
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-calendar2-week"></i> Exam Routine
                </h2>
                <div class="text-muted">Add subject-wise exam date and time for class-specific admit cards.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="manage_exams.php" class="btn btn-primary">
                    <i class="bi bi-calendar-check"></i> Exams
                </a>
                <a href="manage_subjects.php" class="btn btn-info">
                    <i class="bi bi-book"></i> Subjects
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Marks
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$examsTableExists || !$subjectsTableExists || !$classesTableExists): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        Some required tables are missing. The routine page can only work when exams, subjects, and classes are available.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-5">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $editRoutine ? 'pencil' : 'plus'; ?>-circle"></i>
                    <?php echo $editRoutine ? 'Edit Routine' : 'Add Routine'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editRoutine): ?>
                        <input type="hidden" name="routine_id" value="<?php echo intval($editRoutine['routine_id']); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Exam <span class="text-danger">*</span></label>
                        <select name="exam_id" class="form-select" required <?php echo !$examsTableExists ? 'disabled' : ''; ?>>
                            <option value="">-- Select Exam --</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo intval($exam['exam_id']); ?>" <?php echo intval($exam['exam_id']) === intval($selectedExamId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo htmlspecialchars(date('d-M-Y', strtotime($exam['exam_date']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Class <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select" required <?php echo !$classesTableExists ? 'disabled' : ''; ?>>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo intval($class['class_id']); ?>" <?php echo intval($class['class_id']) === intval($selectedClassId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select name="section_id" class="form-select" <?php echo !$sectionsTableExists ? 'disabled' : ''; ?>>
                            <option value="0">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo intval($section['section_id']); ?>" <?php echo intval($section['section_id']) === intval($selectedSectionId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Use All Sections when the same routine applies to the whole class.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required <?php echo !$subjectsTableExists ? 'disabled' : ''; ?>>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjectRows as $subject): ?>
                                <option value="<?php echo intval($subject['subject_id']); ?>" <?php echo intval($subject['subject_id']) === intval($selectedSubjectId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    <?php if (!empty($subject['subject_code'])): ?>
                                        (<?php echo htmlspecialchars($subject['subject_code']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam Date <span class="text-danger">*</span></label>
                            <input type="date" name="exam_date" class="form-control"
                                   value="<?php echo htmlspecialchars($formValues['exam_date']); ?>"
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" class="form-control"
                                   value="<?php echo htmlspecialchars($formValues['display_order']); ?>"
                                   min="0" step="1">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control"
                                   value="<?php echo htmlspecialchars($formValues['start_time']); ?>"
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control"
                                   value="<?php echo htmlspecialchars($formValues['end_time']); ?>"
                                   required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Room No</label>
                        <input type="text" name="room_no" class="form-control"
                               value="<?php echo htmlspecialchars($formValues['room_no']); ?>"
                               placeholder="Optional room or hall number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Optional instructions for this paper"><?php echo htmlspecialchars($formValues['notes']); ?></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo intval($formValues['is_active']) === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <div class="d-grid gap-2">
                        <?php if ($editRoutine): ?>
                            <button type="submit" name="update_routine" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Update Routine
                            </button>
                            <a href="exam_routine.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_routine" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Save Routine
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card dashboard-card">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> Routine List (<?php echo count($routineRows); ?>)
                </h5>
                <a href="<?php echo APP_URL; ?>/modules/reports/admit_cards.php?<?php echo http_build_query(array_filter([
                    'mode' => 'class',
                    'class_id' => $selectedClassId > 0 ? $selectedClassId : null,
                    'section_id' => $selectedSectionId > 0 ? $selectedSectionId : null,
                    'exam_id' => $selectedExamId > 0 ? $selectedExamId : null,
                ])); ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-card-heading"></i> Preview Admit Card
                </a>
            </div>
            <div class="card-body">
                <?php if (count($routineRows) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Exam</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routineRows as $routine): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($routine['exam_name'] ?? '-'); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($routine['exam_type'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars(examRoutineBuildClassLabel($routine)); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($routine['subject_name'] ?? '-'); ?>
                                            <?php if (!empty($routine['subject_code'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($routine['subject_code']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d-M-Y', strtotime($routine['exam_date'] ?? 'now'))); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars(admitCardBuildTimeLabel($routine['start_time'] ?? '', $routine['end_time'] ?? '')); ?>
                                            <?php if (!empty($routine['room_no'])): ?>
                                                <br><small class="text-muted">Room: <?php echo htmlspecialchars($routine['room_no']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (intval($routine['is_active'] ?? 0) === 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="?edit=<?php echo intval($routine['routine_id']); ?>" class="btn btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this routine row?');">
                                                    <input type="hidden" name="routine_id" value="<?php echo intval($routine['routine_id']); ?>">
                                                    <button type="submit" name="delete_routine" class="btn btn-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        No routine rows found yet. Add the first paper using the form on the left.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-primary mt-3">
            <h6 class="mb-2"><i class="bi bi-lightbulb"></i> How to use this page</h6>
            <ol class="mb-0 ps-3">
                <li>Select the exam, class, and section.</li>
                <li>Pick the subject and enter the exact exam date and time.</li>
                <li>Save one row for each paper.</li>
                <li>The admit card will read these routine rows automatically.</li>
            </ol>
        </div>
    </div>
</div>

<?php
include '../../includes/footer.php';
