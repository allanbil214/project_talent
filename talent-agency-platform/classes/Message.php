<?php
// classes/Message.php

class Message {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function getAdminStats() {
        return [
            'total_conversations' => $this->db->fetchColumn("SELECT COUNT(*) FROM conversations"),
            'total_messages'      => $this->db->fetchColumn("SELECT COUNT(*) FROM messages"),
            'today_messages'      => $this->db->fetchColumn("SELECT COUNT(*) FROM messages WHERE DATE(sent_at) = CURDATE()"),
            'unread_messages'     => $this->db->fetchColumn("SELECT COUNT(*) FROM messages WHERE read_status = 0"),
        ];
    }

    public function getConversations($search = '', $limit = 50) {
        $search_sql = $search ? "HAVING participant_names LIKE ?" : '';
        $params = $search ? ['%' . $search . '%'] : [];
    
        return $this->db->fetchAll(
            "SELECT c.id, c.created_at,
                    MAX(m.sent_at) AS last_message_at,
                    COUNT(DISTINCT m.id) AS message_count,
                    SUM(CASE WHEN m.read_status = 0 THEN 1 ELSE 0 END) AS unread_count,
                    (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) AS last_message,
                    GROUP_CONCAT(DISTINCT CASE WHEN u.role='talent' THEN t.full_name WHEN u.role='employer' THEN e.company_name ELSE u.email END ORDER BY u.id SEPARATOR ', ') AS participant_names,
                    GROUP_CONCAT(DISTINCT u.role ORDER BY u.id SEPARATOR ', ') AS participant_roles
             FROM conversations c
             LEFT JOIN messages m ON m.conversation_id = c.id
             LEFT JOIN conversation_participants cp ON cp.conversation_id = c.id
             LEFT JOIN users u ON u.id = cp.user_id
             LEFT JOIN talents t ON t.user_id = u.id
             LEFT JOIN employers e ON e.user_id = u.id
             GROUP BY c.id $search_sql
             ORDER BY last_message_at DESC LIMIT ?",
            array_merge($params, [$limit])
        );
    }

    public function getByConversation($conversation_id) {
        return $this->db->fetchAll(
            "SELECT m.*,
                    CASE WHEN u.role='talent' THEN t.full_name WHEN u.role='employer' THEN e.company_name ELSE u.email END AS sender_name,
                    u.role AS sender_role, t.profile_photo_url
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             LEFT JOIN talents t ON t.user_id = u.id
             LEFT JOIN employers e ON e.user_id = u.id
             WHERE m.conversation_id = ?
             ORDER BY m.sent_at ASC",
            [$conversation_id]
        );
    }

    public function getParticipants($conversation_id) {
        return $this->db->fetchAll(
            "SELECT u.id, u.email, u.role,
                    CASE WHEN u.role='talent' THEN t.full_name WHEN u.role='employer' THEN e.company_name ELSE u.email END AS display_name,
                    t.profile_photo_url
             FROM conversation_participants cp
             JOIN users u ON u.id = cp.user_id
             LEFT JOIN talents t ON t.user_id = u.id
             LEFT JOIN employers e ON e.user_id = u.id
             WHERE cp.conversation_id = ?",
            [$conversation_id]
        );
    }
}