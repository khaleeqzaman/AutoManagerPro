<?php
require_once 'config/database.php';
require_once 'core/Database.php';
require_once 'core/Auth.php';
require_once 'core/functions.php';
require_once 'core/Permissions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Seed default permissions if table is empty
$db = Database::getInstance();
$count = $db->fetchOne("SELECT COUNT(*) as cnt FROM role_permissions")['cnt'] ?? 0;
if ($count === 0) Permissions::seed($db);

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            if (Auth::login($email, $password)) {
                redirect('dashboard/index.php');
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #060d1a; min-height: 100vh; }
        .bg-mesh {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(29,78,216,0.25) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 90%, rgba(30,58,138,0.2) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 50% 50%, rgba(15,23,42,0.9) 0%, transparent 100%);
        }
        .dot-grid {
            position: fixed; inset: 0; z-index: 0;
            background-image: radial-gradient(rgba(148,163,184,0.08) 1px, transparent 1px);
            background-size: 28px 28px;
        }
        .card {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(148, 163, 184, 0.12);
            box-shadow: 0 0 0 1px rgba(255,255,255,0.04) inset, 0 32px 64px rgba(0,0,0,0.5);
        }
        .input-wrap input {
            background: rgba(30, 41, 59, 0.8);
            border: 1.5px solid rgba(148, 163, 184, 0.15);
            color: #f1f5f9; font-size: 0.925rem;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            caret-color: #60a5fa;
        }
        .input-wrap input::placeholder { color: #64748b; }
        .input-wrap input:focus {
            outline: none; border-color: #3b82f6;
            background: rgba(30, 41, 59, 1);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.18);
        }
        .input-wrap input.error-border {
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(248,113,113,0.15);
        }
        label {
            color: #94a3b8; font-size: 0.8rem; font-weight: 600;
            letter-spacing: 0.04em; text-transform: uppercase;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff; font-weight: 700; font-size: 0.925rem;
            letter-spacing: 0.02em; transition: all 0.25s;
            box-shadow: 0 4px 20px rgba(37,99,235,0.35);
        }
        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(37,99,235,0.45);
        }
        .btn-primary:active:not(:disabled) { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
        .brand-icon {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            box-shadow: 0 8px 32px rgba(37,99,235,0.4);
        }
        .badge {
            background: rgba(30,41,59,0.8);
            border: 1px solid rgba(148,163,184,0.12);
            color: #64748b; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.03em;
        }
        .badge i { color: #60a5fa; }
        .divider-text { color: #334155; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        @keyframes shake {
            0%,100% { transform: translateX(0); } 20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }  60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
        }
        .float-anim { animation: float 5s ease-in-out infinite; }
        .shake-anim { animation: shake 0.4s ease; }
        .error-msg {
            color: #fca5a5; font-size: 0.75rem; font-weight: 500;
            margin-top: 5px; display: flex; align-items: center; gap: 5px;
        }
        .alert-error {
            background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5; border-radius: 12px; padding: 12px 16px;
            font-size: 0.85rem; display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
        }
        .toggle-pw { color: #475569; transition: color 0.2s; background: none; border: none; cursor: pointer; padding: 0 4px; }
        .toggle-pw:hover { color: #60a5fa; }
        .footer-text { color: #1e293b; font-size: 0.72rem; text-align: center; margin-top: 24px; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="bg-mesh"></div>
    <div class="dot-grid"></div>

    <div class="relative z-10 w-full max-w-[420px]">

        <!-- Brand -->
        <div class="text-center mb-8">
            <div class="float-anim inline-block mb-4">
                <div class="brand-icon w-16 h-16 rounded-2xl flex items-center justify-center mx-auto">
                    <i class="fas fa-car-side text-white text-2xl"></i>
                </div>
            </div>
            <h1 class="text-white text-[1.6rem] font-bold tracking-tight leading-tight">AutoManager Pro</h1>
            <p class="text-blue-400 text-[0.8rem] font-600 mt-1 tracking-wide uppercase" style="letter-spacing:0.12em;">
                Car Showroom Management
            </p>
        </div>

        <!-- Card -->
        <div class="card rounded-2xl p-8" id="loginCard">

            <h2 class="text-white text-lg font-700 mb-1">Welcome back</h2>
            <p class="text-slate-400 text-sm mb-6">Sign in to access your dashboard</p>

            <?php if (!empty($error)): ?>
            <div class="alert-error" id="alertBox">
                <i class="fas fa-circle-exclamation flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- Email -->
                <div class="mb-5 input-wrap">
                    <label for="email" class="block mb-2">
                        <i class="fas fa-envelope mr-1 text-blue-500"></i> Email Address
                    </label>
                    <input type="email" name="email" id="email"
                           class="w-full px-4 py-3 rounded-xl"
                           placeholder="admin@showroom.com"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                           autocomplete="email">
                    <div class="error-msg hidden" id="emailError">
                        <i class="fas fa-circle-exclamation"></i><span></span>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-7 input-wrap">
                    <label for="password" class="block mb-2">
                        <i class="fas fa-lock mr-1 text-blue-500"></i> Password
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password"
                               class="w-full px-4 py-3 pr-11 rounded-xl"
                               placeholder="Enter your password"
                               autocomplete="current-password">
                        <button type="button" class="toggle-pw absolute right-3 top-1/2 -translate-y-1/2" id="togglePw">
                            <i class="fas fa-eye text-sm" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="error-msg hidden" id="passError">
                        <i class="fas fa-circle-exclamation"></i><span></span>
                    </div>
                </div>

                <button type="submit" id="submitBtn"
                        class="btn-primary w-full py-3 rounded-xl flex items-center justify-center gap-2">
                    <i class="fas fa-right-to-bracket" id="btnIcon"></i>
                    <span id="btnText">Sign In</span>
                </button>
            </form>

            <div class="flex items-center gap-3 my-5">
                <div class="flex-1 h-px" style="background:rgba(148,163,184,0.08)"></div>
                <span class="divider-text">SYSTEM</span>
                <div class="flex-1 h-px" style="background:rgba(148,163,184,0.08)"></div>
            </div>

            <div class="flex flex-wrap gap-2 justify-center">
                <span class="badge px-3 py-1 rounded-full flex items-center gap-1.5">
                    <i class="fas fa-shield-halved"></i> CSRF Protected
                </span>
                <span class="badge px-3 py-1 rounded-full flex items-center gap-1.5">
                    <i class="fas fa-users"></i> Role Based
                </span>
                <span class="badge px-3 py-1 rounded-full flex items-center gap-1.5">
                    <i class="fas fa-lock"></i> Encrypted
                </span>
            </div>
        </div>

        <p class="footer-text">&copy; <?= date('Y') ?> AutoManager Pro &mdash; All rights reserved.</p>
    </div>

    <script>
        document.getElementById('togglePw').addEventListener('click', function () {
            const pw   = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pw.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        document.getElementById('loginForm').addEventListener('submit', function (e) {
            let valid = true;
            const email    = document.getElementById('email');
            const password = document.getElementById('password');
            const emailErr = document.getElementById('emailError');
            const passErr  = document.getElementById('passError');

            [emailErr, passErr].forEach(el => el.classList.add('hidden'));
            [email, password].forEach(el => el.classList.remove('error-border'));

            const showErr = (el, errEl, msg) => {
                el.classList.add('error-border');
                errEl.querySelector('span').textContent = msg;
                errEl.classList.remove('hidden');
                valid = false;
            };

            if (!email.value.trim()) {
                showErr(email, emailErr, 'Email address is required.');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                showErr(email, emailErr, 'Please enter a valid email address.');
            }

            if (!password.value) {
                showErr(password, passErr, 'Password is required.');
            }

            if (!valid) {
                e.preventDefault();
                const card = document.getElementById('loginCard');
                card.classList.add('shake-anim');
                setTimeout(() => card.classList.remove('shake-anim'), 400);
                return;
            }

            const btn  = document.getElementById('submitBtn');
            const icon = document.getElementById('btnIcon');
            const text = document.getElementById('btnText');
            btn.disabled     = true;
            icon.className   = 'fas fa-spinner fa-spin';
            text.textContent = 'Signing in...';
        });

        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            setTimeout(() => {
                alertBox.style.transition = 'opacity 0.5s ease';
                alertBox.style.opacity    = '0';
                setTimeout(() => alertBox.remove(), 500);
            }, 5000);
        }
    </script>
</body>
</html>