<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Our Store</title>
</head>
<body>
    <h2>Hello {{ $user->name ?? $user->email }},</h2>
    <p>Your account has been created successfully while placing your order.</p>
    <p>You can now log in using your email: <strong>{{ $user->email }}</strong></p>
    <p>Thank you for shopping with us!</p>
</body>
</html>