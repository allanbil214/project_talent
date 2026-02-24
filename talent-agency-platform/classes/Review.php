<?php
// classes/Review.php

class Review {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function getRecentByReviewee($user_id, $limit = 5) {
        return $this->db->fetchAll(
            "SELECT r.*, u.email AS reviewer_email
             FROM reviews r
             JOIN users u ON u.id = r.reviewer_id
             WHERE r.reviewee_id = ?
             ORDER BY r.created_at DESC LIMIT ?",
            [$user_id, $limit]
        );
    }
}
