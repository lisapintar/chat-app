<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Chat App</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .card {
            background: #ffffff;
            border: 1px solid #aaaaaa;
            border-radius: 4px;
            padding: 32px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 24px;
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 20px;
        }
        .logo h1 { color: #111111; font-size: 20px; font-weight: 700; }
        .logo p  { color: #666666; font-size: 12px; margin-top: 4px; }
        .form-group { margin-bottom: 14px; }
        label {
            display: block;
            color: #333333;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            background: #ffffff;
            border: 1px solid #aaaaaa;
            border-radius: 3px;
            padding: 9px 12px;
            color: #111111;
            font-size: 13px;
            outline: none;
        }
        input:focus { border-color: #111111; }
        .error { color: #cc0000; font-size: 12px; margin-top: 4px; }
        .btn {
            width: 100%;
            background: #111111;
            color: #ffffff;
            border: none;
            border-radius: 3px;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
        }
        .btn:hover { background: #333333; }
        .remember {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
        }
        .remember input { width: auto; }
        .remember label { margin: 0; font-weight: 400; color: #555555; font-size: 12px; }
        .footer-link {
            text-align: center;
            margin-top: 20px;
            color: #666666;
            font-size: 12px;
            padding-top: 16px;
            border-top: 1px solid #eeeeee;
        }
        .footer-link a { color: #111111; font-weight: 600; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <h1>Chat App</h1>
            <p>Masuk ke akun kamu</p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       placeholder="nama@email.com" required autofocus>
                @error('email')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••" required>
                @error('password')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>
            <div class="remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Ingat saya</label>
            </div>
            <button type="submit" class="btn">Masuk</button>
        </form>

        <div class="footer-link">
            Belum punya akun? <a href="{{ route('register') }}">Daftar</a>
        </div>
    </div>
</body>
</html>
