<?php
// classes/Skill.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class Skill {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create new skill
     */
    public function create($data) {
        // Check if skill already exists
        if ($this->nameExists($data['name'])) {
            logActivity($this->db, 'skill_creation_failed', 
                "Attempted to create duplicate skill: '{$data['name']}'");
            throw new Exception('Skill already exists');
        }
        
        $sql = "INSERT INTO skills (name, category, created_at) 
                VALUES (?, ?, NOW())";
        
        $skill_id = $this->db->insert($sql, [
            $data['name'],
            $data['category'] ?? null
        ]);
        
        // Log skill creation
        logActivity($this->db, 'skill_created', 
            "Skill #{$skill_id} created. Name: '{$data['name']}', " .
            "Category: " . ($data['category'] ?? 'Uncategorized'));
        
        return $skill_id;
    }
    
    /**
     * Update skill
     */
    public function update($id, $data) {
        // Get current skill info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'skill_update_failed', 
                "Attempted to update non-existent skill #{$id}");
            throw new Exception('Skill not found');
        }
        
        // Check if new name already exists (excluding current skill)
        if (isset($data['name']) && $data['name'] !== $current['name'] && 
            $this->nameExists($data['name'], $id)) {
            logActivity($this->db, 'skill_update_failed', 
                "Attempted to update skill #{$id} to duplicate name: '{$data['name']}'");
            throw new Exception('Skill name already exists');
        }
        
        $allowed_fields = ['name', 'category'];
        $set_clauses = [];
        $params = [];
        $changes = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_clauses[] = "{$field} = ?";
                $params[] = $value;
                
                // Track changes
                if ($current[$field] != $value) {
                    $old_value = $current[$field] ?: 'null';
                    $new_value = $value ?: 'null';
                    $changes[] = "{$field}: '{$old_value}' → '{$new_value}'";
                }
            }
        }
        
        if (empty($set_clauses)) {
            return false;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE skills SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Log updates if something changed
        if ($result && !empty($changes)) {
            logActivity($this->db, 'skill_updated', 
                "Skill #{$id} ('{$current['name']}') updated. Changes: " . implode(', ', $changes));
        }
        
        return $result;
    }
    
    /**
     * Delete skill
     */
    public function delete($id) {
        // Get current skill info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'skill_delete_failed', 
                "Attempted to delete non-existent skill #{$id}");
            throw new Exception('Skill not found');
        }
        
        // Get counts of associations for logging
        $talent_count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM talent_skills WHERE skill_id = ?", 
            [$id]
        );
        
        $job_count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM job_skills WHERE skill_id = ?", 
            [$id]
        );
        
        // Delete talent_skills associations first
        $this->db->delete("DELETE FROM talent_skills WHERE skill_id = ?", [$id]);
        
        // Delete job_skills associations
        $this->db->delete("DELETE FROM job_skills WHERE skill_id = ?", [$id]);
        
        // Delete skill
        $sql = "DELETE FROM skills WHERE id = ?";
        $result = $this->db->delete($sql, [$id]);
        
        // Log skill deletion
        if ($result) {
            logActivity($this->db, 'skill_deleted', 
                "Skill #{$id} ('{$current['name']}') deleted. " .
                "Category: '{$current['category']}', " .
                "Removed from {$talent_count} talents and {$job_count} jobs.");
        }
        
        return $result;
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
    
    // Read operations - no logging needed (keep as is)
    
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
        // Log when someone searches skills (optional - could be noisy)
        // if (isset($_SESSION['user_id'])) {
        //     logActivity($this->db, 'skills_searched', 
        //         "User #{$_SESSION['user_id']} searched skills with term: '{$keyword}'");
        // }
        
        $sql = "SELECT * FROM skills WHERE name LIKE ? ORDER BY name ASC LIMIT 20";
        return $this->db->fetchAll($sql, ['%' . $keyword . '%']);
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
    
    /**
     * Bulk import skills (admin feature)
     */
    public function bulkImport($skills) {
        $created = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($skills as $skill) {
            try {
                $name = trim($skill['name'] ?? $skill);
                $category = $skill['category'] ?? null;
                
                if (empty($name)) continue;
                
                if ($this->nameExists($name)) {
                    $skipped++;
                    continue;
                }
                
                $this->create([
                    'name' => $name,
                    'category' => $category
                ]);
                $created++;
                
            } catch (Exception $e) {
                $errors[] = "Failed to import '{$name}': " . $e->getMessage();
            }
        }
        
        // Log bulk import
        logActivity($this->db, 'skills_bulk_imported', 
            "Bulk import completed. Created: {$created}, Skipped: {$skipped}" . 
            (!empty($errors) ? ", Errors: " . implode('; ', $errors) : ""));
        
        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
}