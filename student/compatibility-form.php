<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$name = $_SESSION['name'] ?? 'Student';
$email = $_SESSION['email'] ?? '';
$institutional_id = $_SESSION['institutional_id'] ?? '';

$success_msg = '';
$error_msg = '';
$info_msg = '';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['after_application'])) {
    $info_msg = "Your hostel application has been submitted successfully. Please complete your Roommate Compatibility Form so the admin can assign a suitable roommate.";
}

if (isset($_GET['required'])) {
    $info_msg = "You must complete the Roommate Compatibility Form before your room request can be fully processed.";
}

/* Get latest hostel application */
$app_stmt = mysqli_prepare(
    $conn,
    "SELECT id, compatibility_completed 
     FROM student_records 
     WHERE user_id = ? 
     AND is_deleted = 0
     ORDER BY id DESC 
     LIMIT 1"
);

mysqli_stmt_bind_param($app_stmt, "i", $user_id);
mysqli_stmt_execute($app_stmt);
$app_result = mysqli_stmt_get_result($app_stmt);
$application = mysqli_fetch_assoc($app_result);
mysqli_stmt_close($app_stmt);

if (!$application) {
    $error_msg = "You need to submit the hostel application form first.";
}

$student_record_id = $application ? intval($application['id']) : 0;

/* Get existing compatibility data */
$preference = null;

$pref_stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM roommate_preferences WHERE user_id = ? LIMIT 1"
);

mysqli_stmt_bind_param($pref_stmt, "i", $user_id);
mysqli_stmt_execute($pref_stmt);
$pref_result = mysqli_stmt_get_result($pref_stmt);
$preference = mysqli_fetch_assoc($pref_result);
mysqli_stmt_close($pref_stmt);

/* Form submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $application) {
    $gender = $_POST['gender'] ?? '';
    $preferred_hall = $_POST['preferred_hall'] ?? '';
    $sleep_time = $_POST['sleep_time'] ?? '';
    $wake_time = $_POST['wake_time'] ?? '';
    $study_habit = $_POST['study_habit'] ?? '';
    $cleanliness = $_POST['cleanliness'] ?? '';
    $noise_tolerance = $_POST['noise_tolerance'] ?? '';
    $guest_preference = $_POST['guest_preference'] ?? '';
    $personality = $_POST['personality'] ?? '';
    $religion_sensitive = $_POST['religion_sensitive'] ?? '';
    $additional_note = trim($_POST['additional_note'] ?? '');

    $allowed = [
        'gender' => ['male', 'female'],
        'female_halls' => ['rasg1', 'rasg2'],
        'male_halls' => ['yksg1', 'yksg2'],
        'sleep_time' => ['early', 'medium', 'late'],
        'wake_time' => ['early', 'medium', 'late'],
        'study_habit' => ['quiet', 'normal', 'group_study'],
        'cleanliness' => ['very_clean', 'normal', 'relaxed'],
        'noise_tolerance' => ['low', 'medium', 'high'],
        'guest_preference' => ['rarely', 'sometimes', 'often'],
        'personality' => ['introvert', 'balanced', 'extrovert'],
        'religion_sensitive' => ['yes', 'no']
    ];

    if (!in_array($gender, $allowed['gender'])) {
        $error_msg = "Please select gender.";
    } elseif ($gender === 'female' && !in_array($preferred_hall, $allowed['female_halls'])) {
        $error_msg = "Female students can select only RASG1 or RASG2.";
    } elseif ($gender === 'male' && !in_array($preferred_hall, $allowed['male_halls'])) {
        $error_msg = "Male students can select only YKSG1 or YKSG2.";
    } elseif (!in_array($sleep_time, $allowed['sleep_time'])) {
        $error_msg = "Please select sleep time.";
    } elseif (!in_array($wake_time, $allowed['wake_time'])) {
        $error_msg = "Please select wake-up time.";
    } elseif (!in_array($study_habit, $allowed['study_habit'])) {
        $error_msg = "Please select study habit.";
    } elseif (!in_array($cleanliness, $allowed['cleanliness'])) {
        $error_msg = "Please select cleanliness level.";
    } elseif (!in_array($noise_tolerance, $allowed['noise_tolerance'])) {
        $error_msg = "Please select noise tolerance.";
    } elseif (!in_array($guest_preference, $allowed['guest_preference'])) {
        $error_msg = "Please select guest preference.";
    } elseif (!in_array($personality, $allowed['personality'])) {
        $error_msg = "Please select personality type.";
    } elseif (!in_array($religion_sensitive, $allowed['religion_sensitive'])) {
        $error_msg = "Please select religious/cultural sensitivity.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO roommate_preferences 
            (
                user_id,
                student_record_id,
                gender,
                preferred_hall,
                sleep_time,
                wake_time,
                study_habit,
                cleanliness,
                noise_tolerance,
                guest_preference,
                personality,
                religion_sensitive,
                additional_note
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                student_record_id = VALUES(student_record_id),
                gender = VALUES(gender),
                preferred_hall = VALUES(preferred_hall),
                sleep_time = VALUES(sleep_time),
                wake_time = VALUES(wake_time),
                study_habit = VALUES(study_habit),
                cleanliness = VALUES(cleanliness),
                noise_tolerance = VALUES(noise_tolerance),
                guest_preference = VALUES(guest_preference),
                personality = VALUES(personality),
                religion_sensitive = VALUES(religion_sensitive),
                additional_note = VALUES(additional_note),
                updated_at = CURRENT_TIMESTAMP"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iisssssssssss",
            $user_id,
            $student_record_id,
            $gender,
            $preferred_hall,
            $sleep_time,
            $wake_time,
            $study_habit,
            $cleanliness,
            $noise_tolerance,
            $guest_preference,
            $personality,
            $religion_sensitive,
            $additional_note
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            $update_stmt = mysqli_prepare(
                $conn,
                "UPDATE student_records 
                 SET compatibility_completed = 1 
                 WHERE id = ? AND user_id = ?"
            );

            mysqli_stmt_bind_param($update_stmt, "ii", $student_record_id, $user_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);

            $_SESSION['success_msg'] = "Roommate Compatibility Form submitted successfully. Your room request is now complete.";
            header("Location: room-application.php");
            exit();
        } else {
            $error_msg = "Failed to submit compatibility form. Please check database columns and try again.";
            mysqli_stmt_close($stmt);
        }
    }
}

function selected($preference, $field, $value)
{
    if ($preference && isset($preference[$field]) && $preference[$field] === $value) {
        return 'selected';
    }
    return '';
}

$saved_gender = $preference['gender'] ?? '';
$saved_hall = $preference['preferred_hall'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Roommate Compatibility Form - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f8f7;
            color: #1f2937;
        }

        .container {
            max-width: 950px;
            margin: 0 auto;
            background: #ffffff;
            color: #1f2937;
            padding: 30px;
            border-radius: 14px;
            border: 1px solid #d9eeee;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: center;
            border-bottom: 3px solid #00897b;
            padding-bottom: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .header h1 {
            margin: 0;
            color: #004d40;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            font-size: 14px;
        }

        .btn-home {
            background: #64748b;
            color: white;
        }

        .btn-primary {
            background: #00897b;
            color: white;
        }

        .btn-submit {
            width: 100%;
            margin-top: 10px;
            padding: 14px;
            background: #00897b;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
        }

        .info-box {
            background: #e0f2f1;
            color: #004d40;
            border-left: 5px solid #00897b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 5px solid #dc3545;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
            color: #1f2937;
        }

        select,
        textarea {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1f2937;
            font-size: 15px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .small-note {
            margin-top: 6px;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .form-group label {
            color: #7dd3fc;
        }

        body.dark-mode select,
        body.dark-mode textarea {
            background: #1e293b;
            color: #e5e7eb;
            border: 1px solid #334155;
        }

        body.dark-mode .small-note {
            color: #cbd5e1;
        }

        @media (max-width: 750px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="header">
            <h1>Roommate Compatibility Form</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
                <a href="dashboard.php" class="btn btn-home">Student Dashboard</a>
                <a href="../index.php" class="btn btn-home">Home</a>
            </div>
        </div>

        <?php if ($info_msg !== ''): ?>
            <div class="info-box"><?php echo h($info_msg); ?></div>
        <?php endif; ?>

        <?php if ($success_msg !== ''): ?>
            <div class="alert-success"><?php echo h($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg !== ''): ?>
            <div class="alert-error"><?php echo h($error_msg); ?></div>
        <?php endif; ?>

        <?php if ($application): ?>

            <div class="info-box">
                <strong>Student:</strong> <?php echo h($name); ?><br>
                <strong>ID:</strong> <?php echo h($institutional_id); ?><br>
                This information will help the admin assign a suitable roommate based on hall, gender, lifestyle, and study preferences.
            </div>

            <form method="POST">

                <div class="form-grid">

                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" id="genderSelect" required>
                            <option value="">Select Gender</option>
                            <option value="female" <?php echo selected($preference, 'gender', 'female'); ?>>Female Student</option>
                            <option value="male" <?php echo selected($preference, 'gender', 'male'); ?>>Male Student</option>
                        </select>
                        <div class="small-note">
                            Hall list will change based on gender.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Preferred Hall *</label>
                        <select name="preferred_hall" id="hallSelect" required>
                            <option value="">Select gender first</option>
                        </select>
                        <div class="small-note">
                            Female: RASG1 / RASG2. Male: YKSG1 / YKSG2.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sleep Time *</label>
                        <select name="sleep_time" required>
                            <option value="">Select</option>
                            <option value="early" <?php echo selected($preference, 'sleep_time', 'early'); ?>>Early</option>
                            <option value="medium" <?php echo selected($preference, 'sleep_time', 'medium'); ?>>Medium</option>
                            <option value="late" <?php echo selected($preference, 'sleep_time', 'late'); ?>>Late Night</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Wake-up Time *</label>
                        <select name="wake_time" required>
                            <option value="">Select</option>
                            <option value="early" <?php echo selected($preference, 'wake_time', 'early'); ?>>Early</option>
                            <option value="medium" <?php echo selected($preference, 'wake_time', 'medium'); ?>>Medium</option>
                            <option value="late" <?php echo selected($preference, 'wake_time', 'late'); ?>>Late</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Study Habit *</label>
                        <select name="study_habit" required>
                            <option value="">Select</option>
                            <option value="quiet" <?php echo selected($preference, 'study_habit', 'quiet'); ?>>Quiet Study</option>
                            <option value="normal" <?php echo selected($preference, 'study_habit', 'normal'); ?>>Normal</option>
                            <option value="group_study" <?php echo selected($preference, 'study_habit', 'group_study'); ?>>Group Study</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cleanliness Level *</label>
                        <select name="cleanliness" required>
                            <option value="">Select</option>
                            <option value="very_clean" <?php echo selected($preference, 'cleanliness', 'very_clean'); ?>>Very Clean</option>
                            <option value="normal" <?php echo selected($preference, 'cleanliness', 'normal'); ?>>Normal</option>
                            <option value="relaxed" <?php echo selected($preference, 'cleanliness', 'relaxed'); ?>>Relaxed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Noise Tolerance *</label>
                        <select name="noise_tolerance" required>
                            <option value="">Select</option>
                            <option value="low" <?php echo selected($preference, 'noise_tolerance', 'low'); ?>>Low</option>
                            <option value="medium" <?php echo selected($preference, 'noise_tolerance', 'medium'); ?>>Medium</option>
                            <option value="high" <?php echo selected($preference, 'noise_tolerance', 'high'); ?>>High</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Guest Preference *</label>
                        <select name="guest_preference" required>
                            <option value="">Select</option>
                            <option value="rarely" <?php echo selected($preference, 'guest_preference', 'rarely'); ?>>Rarely</option>
                            <option value="sometimes" <?php echo selected($preference, 'guest_preference', 'sometimes'); ?>>Sometimes</option>
                            <option value="often" <?php echo selected($preference, 'guest_preference', 'often'); ?>>Often</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Personality Type *</label>
                        <select name="personality" required>
                            <option value="">Select</option>
                            <option value="introvert" <?php echo selected($preference, 'personality', 'introvert'); ?>>Introvert</option>
                            <option value="balanced" <?php echo selected($preference, 'personality', 'balanced'); ?>>Balanced</option>
                            <option value="extrovert" <?php echo selected($preference, 'personality', 'extrovert'); ?>>Extrovert</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Religious / Cultural Sensitivity *</label>
                        <select name="religion_sensitive" required>
                            <option value="">Select</option>
                            <option value="yes" <?php echo selected($preference, 'religion_sensitive', 'yes'); ?>>Yes</option>
                            <option value="no" <?php echo selected($preference, 'religion_sensitive', 'no'); ?>>No</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Additional Note</label>
                        <textarea name="additional_note" placeholder="Write any extra roommate preference or important note..."><?php echo $preference ? h($preference['additional_note']) : ''; ?></textarea>
                    </div>

                </div>

                <button type="submit" class="btn-submit">Submit Compatibility Form</button>

            </form>

        <?php else: ?>
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        <?php endif; ?>

    </div>

    <script src="../assets/js/theme.js"></script>

    <script>
        const genderSelect = document.getElementById("genderSelect");
        const hallSelect = document.getElementById("hallSelect");

        const savedHall = "<?php echo h($saved_hall); ?>";

        const halls = {
            female: [{
                    value: "rasg1",
                    label: "RASG1"
                },
                {
                    value: "rasg2",
                    label: "RASG2"
                }
            ],
            male: [{
                    value: "yksg1",
                    label: "YKSG1"
                },
                {
                    value: "yksg2",
                    label: "YKSG2"
                }
            ]
        };

        function updateHallOptions() {
            const gender = genderSelect.value;
            hallSelect.innerHTML = "";

            const defaultOption = document.createElement("option");
            defaultOption.value = "";
            defaultOption.textContent = gender ? "Select Preferred Hall" : "Select gender first";
            hallSelect.appendChild(defaultOption);

            if (!gender || !halls[gender]) {
                return;
            }

            halls[gender].forEach(function(hall) {
                const option = document.createElement("option");
                option.value = hall.value;
                option.textContent = hall.label;

                if (hall.value === savedHall) {
                    option.selected = true;
                }

                hallSelect.appendChild(option);
            });
        }

        if (genderSelect && hallSelect) {
            genderSelect.addEventListener("change", updateHallOptions);
            updateHallOptions();
        }
    </script>

</body>

</html>