<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .button {
            display: inline-block;
            background-color: #4f46e5;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recuperación de contraseña</h1>
        </div>
        <div class="content">
            <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p>
            <p>Para continuar con el proceso, haz clic en el siguiente enlace:</p>
            
            <div style="text-align: center;">
                <a class="button" href="{{ config('app.frontend_url') }}/auth/reset-password?token={{ $token }}&email={{ $email }}">
                    Restablecer contraseña
                </a>
            </div>
            
            <p>Si tú no solicitaste este cambio, puedes ignorar este correo. Tu contraseña actual seguirá siendo válida.</p>
            
            <p>Este enlace expirará en 24 horas por razones de seguridad.</p>
            
            <p>Saludos,<br>El equipo de soporte</p>
        </div>
        <div class="footer">
            <p>Si tienes problemas para hacer clic en el botón "Restablecer contraseña", copia y pega la siguiente URL en tu navegador web:</p>
            <p style="word-break: break-all;">{{ config('app.frontend_url') }}/auth/reset-password?token={{ $token }}&email={{ $email }}</p>
        </div>
    </div>
</body>
</html> 