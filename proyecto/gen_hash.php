<?php
/**
 * Generador de hash bcrypt para contraseñas
 * Acceder en: http://localhost/proyecto/gen_hash.php
 * ELIMINAR este archivo después de usarlo.
 */

$passwords = [
    'Admin123!',
    'Capturista1!',
    'Jefe123!',
    'Cuentas1!',
];

echo '<h2>Hashes bcrypt generados</h2>';
echo '<p style="color:red;font-weight:bold;">⚠️ ELIMINA ESTE ARCHIVO DESPUÉS DE USARLO</p>';
echo '<table border="1" cellpadding="8" style="font-family:monospace;">';
echo '<tr><th>Password</th><th>Hash bcrypt (cost=12)</th></tr>';
foreach ($passwords as $pwd) {
    $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
    echo "<tr><td>{$pwd}</td><td style='word-break:break-all;max-width:600px;'>{$hash}</td></tr>";
}
echo '</table>';
echo '<p>Copia el hash de <strong>Admin123!</strong> y reemplázalo en el SQL antes de importar.</p>';
