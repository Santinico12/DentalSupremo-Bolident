<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function login($username, $password) {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    // Método para obtener el hash de la contraseña
    public function getPasswordHash($username) {
        $stmt = $this->pdo->prepare('SELECT password FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        return $result ? $result['password'] : 'No encontrado';
    }
}
?>