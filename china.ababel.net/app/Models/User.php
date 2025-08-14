<?php
// app/Models/User.php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected $table = 'users';
    
    public function findByUsername($username)
    {
        $sql = "SELECT * FROM {$this->table} WHERE username = ? AND is_active = 1";
        $stmt = $this->db->query($sql, [$username]);
        return $stmt->fetch();
    }
    
    public function updateLastLogin($userId)
    {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE id = ?";
        return $this->db->query($sql, [$userId]);
    }
    
    public function createUser($data)
    {
        // Hash password before saving
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->create($data);
    }
    
    public function changePassword($userId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE {$this->table} SET password = ? WHERE id = ?";
        return $this->db->query($sql, [$hashedPassword, $userId]);
    }
}