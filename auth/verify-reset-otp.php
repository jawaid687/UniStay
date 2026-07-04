<?php
session_start();
require_once '../includes/db.php';

$error = '';
$email = isset($_GET['email']) ? $_GET['email'] : '';

if (empty($email)) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp_array = isset($_POST['otp']) ? $_POST['otp'] : [];
    $submitted_otp = mysqli_real_escape_string($conn, trim(implode('', $otp_array)));
    $email_to_verify = mysqli_real_escape_string($conn, $_POST['email']);

    if (strlen($submitted_otp) < 6) {
        $error = "Please enter the complete 6-digit code.";
    } else {
        $query = "SELECT * FROM users WHERE email = '$email_to_verify'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $current_time = date("Y-m-d H:i:s");
            
            if (empty($user['reset_token']) || $user['reset_token'] !== $submitted_otp) {
                $error = "Invalid reset code. Please try again.";
            } elseif ($user['reset_expiry'] < $current_time) {
                $error = "This code has expired. Please request a new one.";
            } else {
                // Clear the used token
                $update_query = "UPDATE users SET reset_token = NULL, reset_expiry = NULL WHERE email = '$email_to_verify'";
                mysqli_query($conn, $update_query);
                
                $_SESSION['reset_email'] = $email_to_verify;
                header("Location: new-password.php");
                exit();
            }
        } else {
            $error = "User not found.";
        }
    }
}

$page_title = 'Verify Reset Code';
$css_file = 'forgot-password.css';
include '../includes/header.php';
?>

<div class="auth-container" style="text-align: center;">
    
    <div class="otp-header-icon">🔒</div>
    
    <h2>Verify It's You</h2>
    <p style="font-size: 14px; margin-bottom: 15px; color: #666;">
        Enter the 6-digit reset code sent to<br><strong><?php echo htmlspecialchars($email); ?></strong>
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert-error" style="text-align: left;"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form action="verify-reset-otp.php?email=<?php echo urlencode($email); ?>" method="POST">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        
        <div class="otp-input-group">
            <input type="number" name="otp[]" class="otp-box" required maxlength="1" autofocus>
            <input type="number" name="otp[]" class="otp-box" required maxlength="1">
            <input type="number" name="otp[]" class="otp-box" required maxlength="1">
            <input type="number" name="otp[]" class="otp-box" required maxlength="1">
            <input type="number" name="otp[]" class="otp-box" required maxlength="1">
            <input type="number" name="otp[]" class="otp-box" required maxlength="1">
        </div>
        
        <button type="submit" class="btn" style="border-radius: 8px;">Verify Code</button>
    </form>
</div>

<script>
    const otpBoxes = document.querySelectorAll('.otp-box');
    otpBoxes.forEach((box, index) => {
        box.addEventListener('input', (e) => {
            if (e.target.value.length > 1) { e.target.value = e.target.value.slice(0, 1); }
            if (e.target.value.length === 1 && index < otpBoxes.length - 1) { otpBoxes[index + 1].focus(); }
        });
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '' && index > 0) { otpBoxes[index - 1].focus(); }
        });
        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text').slice(0, 6).split('');
            if (pastedData.length > 0) {
                otpBoxes.forEach((b, i) => {
                    b.value = pastedData[i] || '';
                    if(pastedData[i]) b.focus();
                });
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>