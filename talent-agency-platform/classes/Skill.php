<?php
// classes/Skill.php

class Skill {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create new skill
     */
    public function create($data) {
        $sql = "INSERT INTO skills (name, category, created_at) 
                VALUES (?, ?, NOW())";
        
        return $this->db->insert($sql, [
            $data['name'],
            $data['category'] ?? null
        ]);
    }
    
    /**
     * Get skill by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM skills WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Get skill by name
     */
    public function getByName($name) {
        $sql = "SELECT * FROM skills WHERE name = ?";
        return $this->db->fetchOne($sql, [$name]);
    }
    
    /**
     * Get all skills
     */
    public function getAll() {
        $sql = "SELECT * FROM skills ORDER BY category ASC, name ASC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get skills by category
     */
    public function getByCategory($category) {
        $sql = "SELECT * FROM skills WHERE category = ? ORDER BY name ASC";
        return $this->db->fetchAll($sql, [$category]);
    }
    
    /**
     * Get all categories
     */
    public function getCategories() {
        $sql = "SELECT DISTINCT category FROM skills WHERE category IS NOT NULL ORDER BY category ASC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Search skills
     */
    public function search($keyword) {
        $sql = "SELECT * FROM skills WHERE name LIKE ? ORDER BY name ASC LIMIT 20";
        return $this->db->fetchAll($sql, ['%' . $keyword . '%']);
    }
    
    /**
     * Update skill
     */
    public function update($id, $data) {
        $allowed_fields = ['name', 'category'];
        $set_clauses = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_clauses[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        if (empty($set_clauses)) {
            return false;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE skills SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete skill
     */
    public function delete($id) {
        // Delete talent_skills associations first
        $this->db->delete("DELETE FROM talent_skills WHERE skill_id = ?", [$id]);
        
        // Delete job_skills associations
        $this->db->delete("DELETE FROM job_skills WHERE skill_id = ?", [$id]);
        
        // Delete skill
        $sql = "DELETE FROM skills WHERE id = ?";
        return $this->db->delete($sql, [$id]);
    }
    
    /**
     * Check if skill name exists
     */
    public function nameExists($name, $exclude_id = null) {
        $sql = "SELECT COUNT(*) FROM skills WHERE name = ?";
        $params = [$name];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        return $this->db->fetchColumn($sql, $params) > 0;
    }
    
    /**
     * Get skills with talent count
     */
    public function getWithTalentCount() {
        $sql = "SELECT s.*, COUNT(ts.talent_id) as talent_count 
                FROM skills s 
                LEFT JOIN talent_skills ts ON s.id = ts.skill_id 
                GROUP BY s.id 
                ORDER BY talent_count DESC, s.name ASC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get popular skills
     */
    public function getPopular($limit = 10) {
        $sql = "SELECT s.*, COUNT(ts.talent_id) as talent_count 
                FROM skills s 
                INNER JOIN talent_skills ts ON s.id = ts.skill_id 
                GROUP BY s.id 
                ORDER BY talent_count DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
}