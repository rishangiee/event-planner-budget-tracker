<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Torbeles, Jerome B.';
$userEmail = $_SESSION['user_email'] ?? 'jerome.torbeles@email.com';

$updateMessage = '';

// Helper: parse "Last, First M. Suffix" format
function parseFullName($fullName) {
    $firstName = '';
    $lastName = '';
    $middleName = '';
    $suffix = '';
    $suffixes = ['Jr.', 'Sr.', 'Jr', 'Sr', 'II', 'III', 'IV', 'V'];

    $parts = explode(',', $fullName, 2);
    if (count($parts) === 2) {
        $lastName = trim($parts[0]);
        $rest = trim($parts[1]);
        $restParts = preg_split('/\s+/', $rest);

        if (!empty($restParts)) {
            $firstName = $restParts[0];
            if (count($restParts) > 1) {
                $lastPart = $restParts[count($restParts) - 1];
                if (in_array($lastPart, $suffixes, true)) {
                    $suffix = $lastPart;
                    if (count($restParts) > 2) {
                        $middleName = implode(' ', array_slice($restParts, 1, -1));
                    }
                } else {
                    $middleName = implode(' ', array_slice($restParts, 1));
                }
            }
        }
    } else {
        $allParts = preg_split('/\s+/', trim($fullName));
        $firstName = $allParts[0] ?? '';
        $lastName = $allParts[count($allParts) - 1] ?? '';
        if (count($allParts) > 2) {
            $middleName = implode(' ', array_slice($allParts, 1, -1));
        }
    }
    return [$firstName, $lastName, $middleName, $suffix];
}

// Check if photo column exists (graceful fallback)
$hasPhotoColumn = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo'");
    $hasPhotoColumn = ($chk && $chk->rowCount() > 0);
} catch (PDOException $e) {
    $hasPhotoColumn = false;
}

// Check if updated_at column exists (graceful fallback)
$hasUpdatedAtColumn = false;
try {
    $chk2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'updated_at'");
    $hasUpdatedAtColumn = ($chk2 && $chk2->rowCount() > 0);
} catch (PDOException $e) {
    $hasUpdatedAtColumn = false;
}

// Fetch current photo from DB (only if column exists)
$userPhoto = null;
if ($hasPhotoColumn) {
    $photoStmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
    $photoStmt->execute([$userId]);
    $userPhoto = $photoStmt->fetchColumn();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($firstName && $lastName) {
        $fullName = $lastName . ', ' . $firstName;
        if ($middleName) $fullName .= ' ' . $middleName;
        if ($suffix) $fullName .= ' ' . $suffix;

        $photoPath = $userPhoto;

        // Handle photo upload only if column exists
        if ($hasPhotoColumn && !empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
            finfo_close($finfo);

            if (in_array($mime, $allowed, true)) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                if (!$ext) $ext = 'jpg';
                $newFileName = 'user_' . $userId . '_' . time() . '.' . $ext;
                $dest = __DIR__ . '/uploads/' . $newFileName;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    // Delete old photo if exists
                    if ($userPhoto && file_exists(__DIR__ . '/uploads/' . $userPhoto)) {
                        @unlink(__DIR__ . '/uploads/' . $userPhoto);
                    }
                    $photoPath = $newFileName;
                }
            }
        }

        if ($hasPhotoColumn && $hasUpdatedAtColumn) {
            $upd = $pdo->prepare("UPDATE users SET name = ?, firstname = ?, lastname = ?, middlename = ?, suffix = ?, contact_number = ?, address = ?, photo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$fullName, $firstName, $lastName, $middleName ?: null, $suffix ?: null, $contact, $address, $photoPath, $userId]);
        } elseif ($hasPhotoColumn) {
            $upd = $pdo->prepare("UPDATE users SET name = ?, firstname = ?, lastname = ?, middlename = ?, suffix = ?, contact_number = ?, address = ?, photo = ? WHERE id = ?");
            $upd->execute([$fullName, $firstName, $lastName, $middleName ?: null, $suffix ?: null, $contact, $address, $photoPath, $userId]);
        } elseif ($hasUpdatedAtColumn) {
            $upd = $pdo->prepare("UPDATE users SET name = ?, firstname = ?, lastname = ?, middlename = ?, suffix = ?, contact_number = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$fullName, $firstName, $lastName, $middleName ?: null, $suffix ?: null, $contact, $address, $userId]);
        } else {
            $upd = $pdo->prepare("UPDATE users SET name = ?, firstname = ?, lastname = ?, middlename = ?, suffix = ?, contact_number = ?, address = ? WHERE id = ?");
            $upd->execute([$fullName, $firstName, $lastName, $middleName ?: null, $suffix ?: null, $contact, $address, $userId]);
        }

        $_SESSION['user_name'] = $fullName;
        $userName = $fullName;
        $userPhoto = $photoPath;
        $updateMessage = 'Profile updated successfully!';
    }
}

list($firstName, $lastName, $middleName, $suffix) = parseFullName($userName);

// Fetch full user record so contact/address come from the DB
$userRecord = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$userRecord->execute([$userId]);
$userData = $userRecord->fetch();

$profile = [
    'first_name' => $firstName,
    'last_name' => $lastName,
    'middle_name' => $middleName,
    'suffix' => $suffix,
    'email' => $userEmail,
    'address' => $userData['address'] ?? '',
    'contact' => $userData['contact_number'] ?? '',
    'role' => 'User',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - CAVENDIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }

        /* Match booking/dashboard top nav */
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            color: #65676b;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s ease;
            font-size: 12px;
            font-weight: 500;
        }
        .nav-item:hover { background: #f2f3f5; color: #1877f2; }
        .nav-item.active { background: #e4f0ff; color: #1877f2; }
        .nav-item i { font-size: 20px; }

        .filter-dropdown {
            position: absolute;
            right: 0;
            top: 110%;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border: 1px solid #e4e6ea;
            padding: 8px 0;
            min-width: 160px;
            display: none;
            z-index: 100;
        }
        .filter-dropdown.active { display: block; }
        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            color: #1c1e21;
            transition: background 0.15s;
            text-decoration: none;
            background: transparent;
        }
        .filter-option:hover { background: #f0f2f5; }

        .alert-success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border: 1px solid #93d7a3;
            color: #166534;
        }

        .profile-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(53,63,45,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .profile-header {
            height: 160px;
            background: #2563eb;
            position: relative;
        }
        .profile-picture-wrap {
            position: absolute;
            bottom: -50px;
            left: 40px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #ffffff;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .profile-picture-wrap img {
            width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
        }
        .profile-picture-wrap i { font-size: 3rem; color: #60a5fa; }

        .change-photo-badge {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1e40af;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid #fff;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }
        .change-photo-badge:hover { background: #1d4ed8; transform: scale(1.08); }
        #photoInput { display: none; }

        .profile-title-area {
            padding: 70px 40px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a472a;
            letter-spacing: -0.02em;
        }
        .profile-role {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #dcfce7;
            color: #15803d;
            margin-top: 4px;
        }

        .edit-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            background: #22c55e;
            color: #ffffff;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .edit-profile-btn:hover {
            background: #16a34a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34,197,94,0.3);
        }
        .btn-save { background: #1a472a; color: #fff; }
        .btn-save:hover { background: #2d6a4f; }
        .btn-cancel {
            background: #f4f7f2;
            color: #991b1b;
            border: 1px solid #e8ebe3;
        }
        .btn-cancel:hover { background: #fee2e2; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            padding: 0 40px 40px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 20px; }
            .profile-title-area { flex-direction: column; align-items: flex-start; }
        }

        .form-field { display: flex; flex-direction: column; gap: 6px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #4d5b3f;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .required-star { color: #dc2626; font-size: 0.9rem; }
        .optional-tag { font-size: 0.7rem; font-weight: 400; color: #a3b095; margin-left: 4px; }

        .input-box { position: relative; display: flex; align-items: center; }
        .input-box i {
            position: absolute;
            left: 14px;
            color: #7aa370;
            font-size: 0.9rem;
            width: 16px;
            text-align: center;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border: 1.5px solid #e3ebe0;
            border-radius: 12px;
            font-size: 0.92rem;
            color: #353f2d;
            background: #faf9f6;
            font-family: inherit;
            transition: all 0.25s ease;
            outline: none;
        }
        .form-input::placeholder, .form-textarea::placeholder { color: #b8c4a9; }
        .form-input:read-only, .form-textarea:read-only {
            background: #f4f7f2;
            color: #62744f;
            cursor: default;
        }
        .form-input.editable, .form-textarea.editable {
            background: #ffffff;
            border-color: #7aa370;
            box-shadow: 0 0 0 3px rgba(122,163,112,0.12);
        }
        .form-input.editable:focus, .form-textarea.editable:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,0.15);
        }
        .form-textarea {
            padding-left: 14px;
            resize: vertical;
            min-height: 56px;
            max-height: 120px;
        }
        .form-textarea.has-icon { padding-left: 40px; }
    </style>
</head>
<body class="min-h-screen">

    <!-- Facebook-like Top Navigation (same as booking.php/dashboard.php) -->
    <div class="top-nav fixed top-0 left-0 right-0 z-50">
        <div class="flex items-center justify-between px-4 py-2 bg-white border-b border-gray-200 gap-4">
            <a href="dashboard.php" class="flex items-center gap-2 flex-shrink-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gradient-to-r from-blue-600 to-blue-700 shadow-lg">
                    <i class="fas fa-calendar-alt text-white text-lg"></i>
                </div>
                <span class="text-lg font-bold text-gray-900">CAVENDIA</span>
            </a>

            <!-- Centered Navigation -->
            <div class="flex items-center gap-6 flex-1 justify-center px-4">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="calendar.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
                <a href="booking.php" class="nav-item">
                    <i class="fas fa-bookmark"></i>
                    <span>Bookings</span>
                </a>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                <div class="relative">
                    <div class="flex items-center gap-2">
                        <span class="hidden sm:inline text-sm font-semibold text-gray-900">Welcome back, <?php echo htmlspecialchars($userName); ?>!</span>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold cursor-pointer hover:bg-blue-700"
                             role="button" tabindex="0" aria-label="User menu">
                            <?php echo strtoupper(substr($userName, 0, 2)); ?>
                        </div>
                    </div>
                    <div class="filter-dropdown" id="userMenu">
                        <a href="profile.php" class="filter-option"><i class="fas fa-user mr-3"></i>Profile</a>
                        <a href="index.php" class="filter-option"><i class="fas fa-sign-out-alt mr-3"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($updateMessage): ?>
    <div id="updateToast" class="fixed top-20 right-6 z-50 p-4 rounded-2xl shadow-xl alert-success font-semibold flex items-center gap-3 max-w-sm animate-pulse">
        <i class="fas fa-check-circle text-lg"></i>
        <?php echo htmlspecialchars($updateMessage); ?>
    </div>
    <?php endif; ?>

    <main class="pt-24 pb-8 px-6 max-w-6xl mx-auto">
        <div class="mb-4" style="margin-top: 40px;">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-user text-blue-600"></i>
                        <span>My Profile</span>
                    </h2>
                    <p class="text-sm mt-1 text-gray-600">View and manage your personal information</p>
                </div>
            </div>
        </div>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-picture-wrap" id="profilePictureWrap">
                    <?php if ($hasPhotoColumn && $userPhoto && file_exists(__DIR__ . '/uploads/' . $userPhoto)): ?>
                        <img id="profileImg" src="uploads/<?php echo htmlspecialchars($userPhoto); ?>?v=<?php echo time(); ?>" alt="Profile">
                        <i id="profileIcon" class="fas fa-user" style="display:none;"></i>
                    <?php else: ?>
                        <img id="profileImg" src="" alt="Profile" style="display:none;">
                        <i id="profileIcon" class="fas fa-user"></i>
                    <?php endif; ?>

                    <div class="change-photo-badge" onclick="document.getElementById('photoInput').click()" title="Change Photo">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
            </div>

            <div class="profile-title-area">
                <div>
                    <h1 class="profile-name"><?php echo htmlspecialchars($userName); ?></h1>
                    <span class="profile-role">User</span>
                </div>
                <div class="flex items-center gap-3" id="actionButtons">
                    <button id="editBtn" class="edit-profile-btn" onclick="enableEditMode()">
                        <i class="fas fa-pencil-alt"></i> Edit Profile
                    </button>
                </div>
            </div>

            <form id="profileForm" class="form-grid" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <input type="file" name="photo" id="photoInput" accept="image/*" onchange="handlePhotoUpload(event)" style="display:none;">

                <div class="form-field">
                    <label class="form-label">First Name <span class="required-star">*</span></label>
                    <div class="input-box">
                        <i class="fas fa-user"></i>
                        <input type="text" name="first_name" id="firstName" class="form-input" value="<?php echo htmlspecialchars($profile['first_name']); ?>" readonly>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label">Last Name <span class="required-star">*</span></label>
                    <div class="input-box">
                        <i class="fas fa-user"></i>
                        <input type="text" name="last_name" id="lastName" class="form-input" value="<?php echo htmlspecialchars($profile['last_name']); ?>" readonly>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label">Middle Name <span class="optional-tag">(Optional)</span></label>
                    <div class="input-box">
                        <i class="fas fa-user"></i>
                        <input type="text" name="middle_name" id="middleName" class="form-input" value="<?php echo htmlspecialchars($profile['middle_name']); ?>" readonly>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label">Suffix Name <span class="optional-tag">(Optional)</span></label>
                    <div class="input-box">
                        <i class="fas fa-user-tag"></i>
                        <input type="text" name="suffix" id="suffix" class="form-input" value="<?php echo htmlspecialchars($profile['suffix']); ?>" readonly placeholder="Jr., Sr., III, etc.">
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label">Email</label>
                    <div class="input-box">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" class="form-input" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label">Contact Number</label>
                    <div class="input-box">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="contact" id="contact" class="form-input" value="<?php echo htmlspecialchars($profile['contact']); ?>" readonly>
                    </div>
                </div>

                <div class="form-field full-width">
                    <label class="form-label">Address</label>
                    <div class="input-box">
                        <i class="fas fa-map-marker-alt" style="top:14px;"></i>
                        <textarea name="address" id="address" class="form-textarea has-icon" readonly><?php echo htmlspecialchars($profile['address']); ?></textarea>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        function enableEditMode() {
            const btnContainer = document.getElementById('actionButtons');
            document.querySelectorAll('.form-input, .form-textarea').forEach(input => {
                input.removeAttribute('readonly');
                input.classList.add('editable');
            });

            btnContainer.innerHTML = `
                <button class="edit-profile-btn btn-cancel" onclick="cancelEdit()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="edit-profile-btn btn-save" onclick="saveChanges()">
                    <i class="fas fa-check"></i> Save Changes
                </button>
            `;
            document.getElementById('firstName').focus();
        }

        function cancelEdit() {
            const inputs = document.querySelectorAll('.form-input, .form-textarea');
            inputs.forEach(input => {
                input.setAttribute('readonly', true);
                input.classList.remove('editable');
            });

            document.getElementById('actionButtons').innerHTML = `
                <button id="editBtn" class="edit-profile-btn" onclick="enableEditMode()">
                    <i class="fas fa-pencil-alt"></i> Edit Profile
                </button>
            `;
        }

        function saveChanges() {
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();

            if (!firstName || !lastName) {
                alert('First Name and Last Name are required.');
                return;
            }
            document.getElementById('profileForm').submit();
        }

        function handlePhotoUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('profileImg');
                const icon = document.getElementById('profileIcon');
                img.src = e.target.result;
                img.style.display = 'block';
                icon.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }

        // user menu toggle
        (function () {
            const root = document.querySelector('[aria-label="User menu"]')?.parentElement;
            const menu = document.getElementById('userMenu');
            if (!root || !menu) return;

            root.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('active');
            });

            const avatar = root.querySelector('[role="button"], [tabindex]');
            if (avatar) {
                avatar.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        menu.classList.toggle('active');
                    }
                });
            }

            document.addEventListener('click', function () {
                menu.classList.remove('active');
            });
        })();

        // auto-dismiss toast
        document.addEventListener('DOMContentLoaded', () => {
            const toast = document.getElementById('updateToast');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>