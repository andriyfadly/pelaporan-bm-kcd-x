<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk</title>
</head>
<body>
    <main>
        <h1>Masuk</h1>

        @if ($errors->any())
            <p role="alert">Username atau password tidak valid.</p>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            <button type="submit">Masuk</button>
        </form>
    </main>
</body>
</html>