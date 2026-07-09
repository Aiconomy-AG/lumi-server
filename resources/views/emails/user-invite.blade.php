<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lumi Invite</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
    <h2>Welcome to Lumi</h2>

    <p>Your account has been created.</p>

    <p><strong>Email:</strong> {{ $user->email }}</p>
    <p><strong>Temporary password:</strong> {{ $temporaryPassword }}</p>

    <p>Please click the link below to set your password:</p>
    <p>
        <a href="{{ $resetUrl }}">Set password</a>
    </p>

    <p>This link will expire soon. If it expires, ask an admin to resend the invite.</p>
</body>
</html>