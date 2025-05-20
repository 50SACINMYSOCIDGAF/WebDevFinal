<?php
/**
 * Events Page
 * Displays a list of events and allows users to create new events.
 */
session_start();
require_once 'functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$avatar_url = getUserAvatar($user_id);

$conn = getDbConnection();

// Fetch events
// For now, fetch all public events and events created by friends or the user.
// This query can be refined later for more complex event privacy/discovery.
$events_query = "
    SELECT e.*, u.username, u.profile_picture,
           (SELECT COUNT(*) FROM event_attendees ea WHERE ea.event_id = e.id AND ea.status = 'going') as going_count,
           (SELECT COUNT(*) FROM event_attendees ea WHERE ea.event_id = e.id AND ea.status = 'interested') as interested_count,
           (SELECT status FROM event_attendees ea WHERE ea.event_id = e.id AND ea.user_id = ?) as user_attendance_status
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.event_date >= CURDATE() AND (
        e.privacy = 'public'
        OR e.user_id = ?
        OR (e.privacy = 'friends' AND EXISTS (
            SELECT 1 FROM friends
            WHERE (user_id = e.user_id AND friend_id = ? AND status = 'accepted')
            OR (user_id = ? AND friend_id = e.user_id AND status = 'accepted')
        ))
    )
    ORDER BY e.event_date ASC, e.event_time ASC
    LIMIT 20
";

$stmt = $conn->prepare($events_query);
// FIX: Changed "iiiii" to "iiii" as there are only 4 placeholders in the query.
// The placeholders correspond to:
// 1. ea.user_id = ? (for user_attendance_status subquery)
// 2. e.user_id = ? (for events created by the current user)
// 3. friend_id = ? (for friends check, first part of OR)
// 4. user_id = ? (for friends check, second part of OR)
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id); // Line 43
$stmt->execute();
$events_result = $stmt->get_result();

$conn->close();

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&family=Comfortaa:wght@400;600&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Specific styles for events page */
        .events-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .event-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: transform var(--transition-fast), box-shadow var(--transition-fast);
            display: flex;
            flex-direction: column;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow);
        }

        .event-card-header {
            position: relative;
            height: 180px;
            background-color: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .event-card-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-date-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background-color: var(--accent);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
            font-size: 0.9rem;
        }

        .event-date-badge .month {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .event-date-badge .day {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .event-card-body {
            padding: 1.25rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .event-meta-item i {
            width: 18px;
            text-align: center;
            color: var(--accent);
        }

        .event-description {
            font-size: 0.95rem;
            color: var(--text-primary);
            line-height: 1.5;
            margin-bottom: 1rem;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3; /* Limit to 3 lines */
            -webkit-box-orient: vertical;
        }

        .event-card-footer {
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .event-attendees-count {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .event-action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .join-event-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .join-event-btn:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }

        .join-event-btn.interested {
            background-color: var(--warning);
            border-color: var(--warning);
        }
        .join-event-btn.interested:hover {
            background-color: var(--warning); /* Keep color on hover */
            filter: brightness(0.9);
        }

        .join-event-btn.going {
            background-color: var(--success);
            border-color: var(--success);
        }
        .join-event-btn.going:hover {
            background-color: var(--success); /* Keep color on hover */
            filter: brightness(0.9);
        }

        .join-event-btn.joined {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .join-event-btn.joined:hover {
            background-color: var(--bg-secondary);
        }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: var(--text-secondary);
        }
        .empty-state .empty-state-icon i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border);
        }
        .empty-state h3 {
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        /* Create Event Modal specific styles */
        .create-event-modal .modal-body .form-group {
            margin-bottom: 1rem;
        }
        .create-event-modal .modal-body label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
        .create-event-modal .modal-body input[type="text"],
        .create-event-modal .modal-body input[type="date"],
        .create-event-modal .modal-body input[type="time"],
        .create-event-modal .modal-body textarea,
        .create-event-modal .modal-body select {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 1rem;
        }
        .create-event-modal .modal-body textarea {
            resize: vertical;
            min-height: 80px;
        }
        .create-event-modal .modal-body input[type="file"] {
            display: block;
            width: 100%;
            padding: 0.5rem 0;
        }
        .create-event-modal .modal-body .form-hint {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        .create-event-modal .modal-body .image-preview-container {
            width: 100%;
            height: 150px;
            border: 1px dashed var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 1rem;
            background-color: var(--bg-tertiary);
        }
        .create-event-modal .modal-body .image-preview-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .create-event-modal .modal-body .image-preview-container .empty-preview {
            color: var(--text-secondary);
            text-align: center;
        }
        .create-event-modal .modal-body .image-preview-container .empty-preview i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .create-event-modal .modal-body .remove-image-btn {
            background: none;
            border: none;
            color: var(--error);
            font-size: 1.2rem;
            cursor: pointer;
            float: right;
            margin-top: -30px;
            margin-right: 5px;
        }
        .create-event-modal .modal-body .privacy-selector {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .create-event-modal .modal-body .privacy-selector label {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        .create-event-modal .modal-body .privacy-selector input[type="radio"] {
            width: auto;
            margin: 0;
            accent-color: var(--accent);
        }
        .create-event-modal .modal-body .location-map {
            height: 250px;
            width: 100%;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid var(--border);
        }
        .create-event-modal .modal-body .location-search-input {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="page-container">
        <div class="events-container">
            <div class="events-header">
                <h1>Upcoming Events</h1>
                <button class="btn btn-primary" id="create-event-btn">
                    <i class="fas fa-plus"></i> Create Event
                </button>
            </div>

            <div class="events-grid" id="events-grid">
                <?php if ($events_result->num_rows === 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>No Events Yet</h3>
                        <p>Be the first to create an event!</p>
                    </div>
                <?php else: ?>
                    <?php while ($event = $events_result->fetch_assoc()): ?>
                        <div class="event-card">
                            <div class="event-card-header">
                                <?php if (!empty($event['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-image fa-5x" style="color: var(--text-secondary);"></i>
                                <?php endif; ?>
                                <div class="event-date-badge">
                                    <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                    <span class="day"><?php echo date('j', strtotime($event['event_date'])); ?></span>
                                </div>
                            </div>
                            <div class="event-card-body">
                                <h2 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h2>
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar-day"></i>
                                        <span><?php echo date('F j, Y', strtotime($event['event_date'])); ?> at <?php echo date('h:i A', strtotime($event['event_time'])); ?></span>
                                    </div>
                                    <?php if (!empty($event['location_name'])): ?>
                                        <div class="event-meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($event['location_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="event-meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>Created by <a href="profile.php?id=<?php echo $event['user_id']; ?>"><?php echo htmlspecialchars($event['username']); ?></a></span>
                                    </div>
                                </div>
                                <p class="event-description">
                                    <?php echo !empty($event['description']) ? nl2br(htmlspecialchars($event['description'])) : 'No description provided.'; ?>
                                </p>
                            </div>
                            <div class="event-card-footer">
                                <div class="event-attendees-count">
                                    <?php echo $event['going_count']; ?> going, <?php echo $event['interested_count']; ?> interested
                                </div>
                                <div class="event-action-buttons">
                                    <?php if ($event['user_id'] === $user_id): ?>
                                        <button class="btn btn-secondary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    <?php else: ?>
                                        <?php
                                        $join_class = 'join-event-btn';
                                        $join_text = '<i class="fas fa-check"></i> Going';
                                        $join_action = 'going';

                                        if ($event['user_attendance_status'] === 'going') {
                                            $join_class .= ' joined going';
                                            $join_text = '<i class="fas fa-check-circle"></i> Going';
                                            $join_action = 'leave';
                                        } elseif ($event['user_attendance_status'] === 'interested') {
                                            $join_class .= ' joined interested';
                                            $join_text = '<i class="fas fa-star"></i> Interested';
                                            $join_action = 'leave';
                                        }
                                        ?>
                                        <button class="<?php echo $join_class; ?>" data-event-id="<?php echo $event['id']; ?>" data-action="<?php echo $join_action; ?>">
                                            <?php echo $join_text; ?>
                                        </button>
                                        <?php if ($event['user_attendance_status'] !== 'going'): ?>
                                            <button class="btn btn-secondary btn-sm interested-event-btn <?php echo ($event['user_attendance_status'] === 'interested') ? 'joined' : ''; ?>" data-event-id="<?php echo $event['id']; ?>" data-action="interested">
                                                <i class="fas fa-star"></i> Interested
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="create-event-modal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Create New Event</h3>
                <button class="modal-close" id="create-event-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="create-event-form" method="post" action="ajax/create_event.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label for="event-title">Event Title</label>
                        <input type="text" id="event-title" name="title" placeholder="e.g., Campus Coding Meetup" required>
                    </div>

                    <div class="form-group">
                        <label for="event-description">Description</label>
                        <textarea id="event-description" name="description" rows="4" placeholder="Tell us more about your event..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="event-date">Date</label>
                        <input type="date" id="event-date" name="event_date" required>
                    </div>

                    <div class="form-group">
                        <label for="event-time">Time</label>
                        <input type="time" id="event-time" name="event_time">
                    </div>

                    <div class="form-group">
                        <label for="event-location-name">Location Name</label>
                        <input type="text" id="event-location-name" name="location_name" placeholder="e.g., University Library, Room 201">
                        <input type="hidden" name="location_lat" id="event-location-lat">
                        <input type="hidden" name="location_lng" id="event-location-lng">
                        <div class="form-hint">You can also select a location on the map below.</div>
                    </div>

                    <div class="form-group">
                        <label>Event Image (Optional)</label>
                        <div class="image-preview-container">
                            <img id="event-image-preview-img" src="#" alt="Event Image Preview" style="display: none;">
                            <div class="empty-preview" id="event-image-empty-preview">
                                <i class="fas fa-image"></i>
                                <span>No image selected</span>
                            </div>
                        </div>
                        <input type="file" id="event-image-input" name="image" accept="image/*">
                        <button type="button" class="btn btn-secondary btn-sm" id="event-image-upload-btn">
                            <i class="fas fa-upload"></i> Choose Image
                        </button>
                        <button type="button" class="remove-image-btn" id="remove-event-image-btn" style="display: none;">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>

                    <div class="form-group">
                        <label>Privacy</label>
                        <div class="privacy-selector">
                            <label>
                                <input type="radio" name="privacy" value="public" checked> Public
                            </label>
                            <label>
                                <input type="radio" name="privacy" value="friends"> Friends Only
                            </label>
                            <label>
                                <input type="radio" name="privacy" value="private"> Only Me
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Select Location on Map</label>
                        <input type="text" id="event-map-search-input" placeholder="Search for a location on map..." class="location-search-input">
                        <div id="event-location-map" class="location-map"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="create-event-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="create-event-submit" class="btn-primary">Create Event</button>
            </div>
        </div>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places" async defer></script>

    <script src="js/main.js"></script>
    <script src="js/events.js"></script>
</body>
</html>