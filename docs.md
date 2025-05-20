# ConnectHub Codebase Documentation

This document provides an overview of the ConnectHub social media platform's codebase, detailing the purpose of key files, the functions available, their expected parameters, and their return values.

## 1. Project Structure Overview

ConnectHub is a web application built with a traditional LAMP stack (Linux, Apache, MySQL, PHP) and uses HTML, CSS, and JavaScript for the frontend.

* **`admin/`**: Contains files related to the administration panel.

* **`ajax/`**: Houses PHP scripts that serve as AJAX endpoints for dynamic content loading and interactions.

* **`components/`**: Stores reusable PHP components like the navigation bar.

* **`js/`**: Contains JavaScript files for client-side logic.

* **`uploads/`**: Directory for user-uploaded content (profile pictures, post images, cover photos).

* **Root Directory**: Main PHP pages, configuration, and global functions.

## 2. Core Configuration & Global Utilities

These files are fundamental to the application's operation and are typically included or linked across various pages.

### `config.php`

* **Purpose**: Defines database connection parameters and ensures all necessary database tables (`users`, `posts`, `comments`, `likes`, `messages`, `friends`, `reports`, `user_customization`, `notifications`, `saved_posts`, `remember_tokens`) are created if they don't exist.

* **Availability**: Loaded via `require_once` in `functions.php` and other PHP scripts that need database access.

* **Expects**: Database credentials (defined as constants).

* **Returns**: Establishes global database constants and creates tables.

### `functions.php`

* **Purpose**: A collection of reusable PHP helper functions for common tasks like input sanitization, user authentication, database interactions, time formatting, and CSRF token management.

* **Availability**: Included via `require_once` at the beginning of almost every PHP page and AJAX endpoint. Functions defined here are globally available once `functions.php` is included.

* **Key Functions & Expectations**:

    * `sanitize(string $input)`

        * **Purpose**: Cleans user input to prevent XSS and other injection attacks.

        * **Expects**: `$input` (string) - The string to sanitize.

        * **Returns**: `string` - The sanitized string.

    * `isPasswordStrong(string $password)`

        * **Purpose**: Validates if a password meets predefined strength requirements (length, uppercase, lowercase, number, special character).

        * **Expects**: `$password` (string) - The password to check.

        * **Returns**: `bool` - `true` if strong, `false` otherwise.

    * `getDbConnection()`

        * **Purpose**: Establishes and returns a new MySQLi database connection.

        * **Expects**: None (uses constants from `config.php`).

        * **Returns**: `mysqli` - A database connection object.

    * `isLoggedIn()`

        * **Purpose**: Checks if a user is currently logged in by verifying the `$_SESSION['user_id']`.

        * **Expects**: None.

        * **Returns**: `bool` - `true` if logged in, `false` otherwise.

    * `isAdmin()`

        * **Purpose**: Checks if the logged-in user has administrator privileges.

        * **Expects**: None.

        * **Returns**: `bool` - `true` if admin, `false` otherwise.

    * `getUserById(int $userId)`

        * **Purpose**: Retrieves all data for a specific user from the `users` table.

        * **Expects**: `$userId` (int) - The ID of the user.

        * **Returns**: `array|null` - An associative array of user data, or `null` if not found.

    * `getUserAvatar(int $userId, string $size = 'medium')`

        * **Purpose**: Returns the URL for a user's profile picture, or a placeholder if none is set.

        * **Expects**: `$userId` (int) - The ID of the user. `$size` (string, optional) - 'small', 'medium', or 'large' for placeholder sizing.

        * **Returns**: `string` - URL to the avatar image.

    * `getUserCustomization(int $userId)`

        * **Purpose**: Fetches a user's profile customization settings (theme, fonts, colors, custom CSS, music).

        * **Expects**: `$userId` (int) - The ID of the user.

        * **Returns**: `array` - An associative array of customization settings, with defaults if no custom settings exist.

    * `getFriendshipStatus(int $userId, int $friendId)`

        * **Purpose**: Determines the friendship status between two users.

        * **Expects**: `$userId` (int) - The current user's ID. `$friendId` (int) - The other user's ID.

        * **Returns**: `string|bool` - The status ('pending', 'accepted', 'rejected', 'blocked') or `false` if no record exists.

    * `isUserBlocked(int $userId, int $targetId)`

        * **Purpose**: Checks if `$userId` has blocked `$targetId`.

        * **Expects**: `$userId` (int) - The ID of the user performing the check. `$targetId` (int) - The ID of the user being checked against.

        * **Returns**: `bool` - `true` if blocked, `false` otherwise.

    * `formatTimeAgo(string $timestamp)`

        * **Purpose**: Converts a MySQL timestamp into a human-readable "time ago" string (e.g., "2 hours ago").

        * **Expects**: `$timestamp` (string) - A valid MySQL datetime string.

        * **Returns**: `string` - Formatted time string.

    * `generateCSRFToken()`

        * **Purpose**: Generates a new CSRF token if one doesn't exist in the session, and returns it.

        * **Expects**: None.

        * **Returns**: `string` - The CSRF token.

    * `isValidCSRFToken(string $token)`

        * **Purpose**: Validates a submitted CSRF token against the one stored in the session.

        * **Expects**: `$token` (string) - The token to validate.

        * **Returns**: `bool` - `true` if valid, `false` otherwise.

    * `countUnreadMessages(int $userId)`

        * **Purpose**: Counts the number of unread messages for a specific user.

        * **Expects**: `$userId` (int) - The user's ID.

        * **Returns**: `int` - The count of unread messages.

    * `countPendingFriendRequests(int $userId)`

        * **Purpose**: Counts the number of pending friend requests received by a user.

        * **Expects**: `$userId` (int) - The user's ID.

        * **Returns**: `int` - The count of pending requests.

    * `applyUserCustomization(array $customization)`

        * **Purpose**: Generates inline CSS rules based on a user's customization settings.

        * **Expects**: `$customization` (array) - An associative array of customization data (from `getUserCustomization`).

        * **Returns**: `string` - CSS rules.

    * `adjustBrightness(string $hex, int $steps)`

        * **Purpose**: Adjusts the brightness of a given hex color.

        * **Expects**: `$hex` (string) - A hex color code (e.g., '#RRGGBB'). `$steps` (int) - Amount to adjust brightness (-255 to 255).

        * **Returns**: `string` - The adjusted hex color.

    * `createNotification(int $userId, string $type, string $message, int $fromUserId = null, int $contentId = null)`

        * **Purpose**: Creates a new notification record in the database for a user.

        * **Expects**: `$userId` (int) - Recipient. `$type` (string) - Notification type. `$message` (string) - Notification text. `$fromUserId` (int, optional) - User who triggered it. `$contentId` (int, optional) - Related content ID.

        * **Returns**: `bool` - `true` on success, `false` on failure.

    * `setInsertTimestamps(string $table, array &$data)`

        * **Purpose**: Adds `created_at` and `updated_at` (if applicable) timestamps to a data array for insert operations.

        * **Expects**: `$table` (string) - Table name. `&$data` (array) - Reference to the data array.

        * **Returns**: `void` (modifies `$data` by reference).

    * `setUpdateTimestamps(string $table, array &$data)`

        * **Purpose**: Adds `updated_at` (if applicable) timestamp to a data array for update operations.

        * **Expects**: `$table` (string) - Table name. `&$data` (array) - Reference to the data array.

        * **Returns**: `void` (modifies `$data` by reference).

### `styles.css`

* **Purpose**: Defines the visual theme and layout of the entire application. It uses CSS variables for easy theme management.

* **Availability**: Linked in the `<head>` section of all HTML/PHP pages.

* **Expects**: None (pure CSS).

* **Returns**: Visual styling.

### `js/main.js`

* **Purpose**: Contains core JavaScript functionalities used across multiple pages, including notification system, AJAX helpers, and modal management.

* **Availability**: Linked in the `<head>` or before `</body>` of most HTML/PHP pages. Functions are typically attached to the `window` object or are event listeners.

* **Key Functions & Expectations**:

    * `window.showNotification(string message, string type = 'info', number duration = 5000)`

        * **Purpose**: Displays a toast notification to the user.

        * **Expects**: `message` (string) - The text to display. `type` (string, optional) - 'success', 'error', 'warning', 'info'. `duration` (number, optional) - Time in milliseconds before auto-closing.

        * **Returns**: `void` (modifies DOM).

    * `window.fetchWithCSRF(string url, Object options = {})`

        * **Purpose**: A wrapper around the `fetch` API that automatically includes the CSRF token in headers.

        * **Expects**: `url` (string) - The API endpoint. `options` (Object, optional) - Standard `fetch` options (e.g., `method`, `body`, `headers`).

        * **Returns**: `Promise<Object>` - A promise that resolves with the parsed JSON response.

    * `window.submitFormAjax(HTMLFormElement form)`

        * **Purpose**: Submits a given HTML form via AJAX, handling `FormData` and CSRF.

        * **Expects**: `form` (HTMLFormElement) - The form element to submit.

        * **Returns**: `Promise<Object>` - A promise that resolves with the JSON response.

    * `window.openModal(string modalId)`

        * **Purpose**: Opens a modal dialog by adding the 'active' class.

        * **Expects**: `modalId` (string) - The ID of the modal HTML element.

        * **Returns**: `void` (modifies DOM).

    * `window.closeModal(Element modal)`

        * **Purpose**: Closes a modal dialog by removing the 'active' class.

        * **Expects**: `modal` (Element) - The modal HTML element.

        * **Returns**: `void` (modifies DOM).

## 3. JavaScript Modules

These scripts handle page-specific interactivity and AJAX calls.

### `js/posts.js`

* **Purpose**: Manages post creation, display, and interaction (likes, comments, reports).

* **Availability**: Linked in `index.php` and `profile.php`.

* **Key Interactions**:

    * **Post Creation Modal**: Opens a modal (`create-post-modal`) for users to compose new posts.

    * **Image Upload**: Previews selected images for posts.

    * **Location Picker**: Integrates Google Maps (`location-modal`) for adding location data to posts (requires `YOUR_API_KEY` in `index.php`).

    * **Post Submission**: Uses `fetchWithCSRF` to send post data to `ajax/create_post.php`.

    * **Post Menus**: Toggles dropdowns for post-specific actions (edit, delete, save, report).

    * **Reporting Posts**: Opens a modal (`report-modal`) to submit reports to `ajax/report_post.php`.

    * **Comments**: Toggles comment sections, loads comments from `ajax/get_comments.php`, and submits new comments to `ajax/add_comment.php`.

    * **Likes**: Handles liking/unliking posts with optimistic UI updates, communicating with `ajax/like_post.php`.

### `js/messages.js`

* **Purpose**: Manages the real-time messaging interface.

* **Availability**: Linked in `messages.php`.

* **Key Interactions**:

    * **Load Conversations**: Fetches conversation list from `ajax/get_conversations.php`.

    * **Render Conversations**: Dynamically displays conversations with unread counts.

    * **Load Messages**: Fetches message history for a selected conversation from `ajax/get_messages.php`.

    * **Send Message**: Sends messages to `ajax/send_message.php` with optimistic UI updates.

    * **Message Polling**: Periodically checks for new messages from `ajax/get_messages.php`.

    * **Load More Messages**: Implements infinite scrolling for message history.

    * **New Message Modal**: Allows searching for recipients (`ajax/search.php`) to start new conversations.

    * **Textarea Auto-resize**: Adjusts the height of the message input as content is typed.

### `js/profile.js`

* **Purpose**: Handles interactive elements and data updates on the user profile page.

* **Availability**: Linked in `profile.php`.

* **Key Interactions**:

    * **Tab Navigation**: Switches between "Posts", "Photos", "Friends", "Saved", and "Music" tabs.

    * **Profile Picture/Cover Photo Update**: Opens modals (`profile-picture-modal`, `cover-photo-modal`) for image uploads, previews, and submits to `update_profile.php`.

    * **Bio Update**: Opens a modal (`bio-modal`) to edit the user's bio, submitting to `update_profile.php`.

    * **Friend Actions**: Handles "Add Friend", "Accept Request", "Unfriend", and "Unblock" actions by calling `ajax/friend_request.php` or `ajax/block_user.php`.

    * **Message Action**: Redirects to `messages.php` with the selected user's ID.

    * **Block/Report User**: Opens modals (`report-user-modal`) to submit reports to `ajax/report_user.php` or block users via `ajax/block_user.php`.

    * **Profile Music Player**: Controls playback and progress for the user's profile song.

## 4. PHP AJAX Endpoints

These scripts are designed to be called by JavaScript via AJAX requests to perform specific server-side operations and return JSON responses.

* **`ajax/add_comment.php`**

    * **Expects**: `POST` with `post_id` (int), `content` (string), `csrf_token` (string).

    * **Returns**: JSON `{success: bool, message: string, comment: Object}`.

* **`ajax/block_user.php`**

    * **Expects**: `POST` with `user_id` (int), `action` (string: 'block' or 'unblock'), `X-CSRF-Token` header.

    * **Returns**: JSON `{success: bool, message: string}`.

* **`ajax/create_post.php`**

    * **Expects**: `POST` with `content` (string), `privacy` (string), `csrf_token` (string), optional `post_image` (file), `location_lat` (float), `location_lng` (float), `location_name` (string).

    * **Returns**: JSON `{success: bool, message: string, post_id: int}`.

* **`ajax/friend_request.php`**

    * **Expects**: `POST` with `user_id` (int), `action` (string: 'add', 'accept', 'reject', 'cancel', 'unfriend'), `csrf_token` (string).

    * **Returns**: JSON `{success: bool, message: string}`.

* **`ajax/get_comments.php`**

    * **Expects**: `GET` with `post_id` (int).

    * **Returns**: JSON `{success: bool, comments: Array<Object>}`.

* **`ajax/get_conversations.php`**

    * **Expects**: No specific parameters (uses `$_SESSION['user_id']`).

    * **Returns**: JSON `{success: bool, conversations: Array<Object>}`.

* **`ajax/get_messages.php`**

    * **Expects**: `GET` with `user_id` (int), optional `limit` (int), `before_id` (int).

    * **Returns**: JSON `{success: bool, messages: Array<Object>, has_more: bool}`.

* **`ajax/like_post.php`**

    * **Expects**: `POST` with `post_id` (int), `action` (string: 'like' or 'unlike'), `csrf_token` (string).

    * **Returns**: JSON `{success: bool, message: string}`.

* **`ajax/report_post.php`**

    * **Expects**: `POST` with `post_id` (int), `reason` (string), optional `details` (string), `csrf_token` (string).

    * **Returns**: JSON `{success: bool, message: string}`.

* **`ajax/report_user.php`**

    * **Expects**: `POST` with `user_id` (int), `reason` (string), optional `details` (string), `X-CSRF-Token` header.

    * **Returns**: JSON `{success: bool, message: string}`.

* **`ajax/search.php`**

    * **Expects**: `GET` with `q` (string, search query).

    * **Returns**: JSON `Array<Object>` containing search results (users, posts, hashtags).

* **`ajax/send_message.php`**

    * **Expects**: `POST` with `receiver_id` (int), `content` (string), `X-CSRF-Token` header.

    * **Returns**: JSON `{success: bool, message: string, message_data: Object}`.

* **`update_profile.php`**

    * **Purpose**: Handles various profile updates (picture, cover, bio, details, photo sections).

    * **Expects**: `POST` with `action` (string: e.g., 'update_profile_picture', 'update_bio'), `csrf_token` (string), and relevant data (`profile_picture` file, `bio` string, etc.).

    * **Returns**: JSON `{success: bool, message: string, url: string (for images)}` for AJAX requests, or redirects for non-AJAX.

## 5. Admin Panel AJAX Endpoints

These scripts are specifically for the admin dashboard.

* **`admin/ajax/get_post_stats.php`**

    * **Expects**: `GET` with `range` (string: 'week', 'month', 'year'), `csrf_token` (string).

    * **Returns**: JSON `{success: bool, labels: Array<string>, values: Array<int>, range: string}`.

* **`admin/ajax/get_recent_activity.php`**

    * **Expects**: `GET` with `csrf_token` (string).

    * **Returns**: JSON `{success: bool, html: string}` (HTML string of recent activities).

This documentation should provide a solid foundation for understanding the codebase and tackling bug fixes.