USE s4402739_main;

-- Create posts table if it doesn't exist
CREATE TABLE IF NOT EXISTS s4402739_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX idx_posts_user_id ON s4402739_posts(user_id);
CREATE INDEX idx_posts_created_at ON s4402739_posts(created_at);