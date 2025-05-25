import asyncio
import os
from playwright.async_api import async_playwright

# --- Configuration ---
BASE_URL = "https://s4402739-ctxxxx.uogs.co.uk/"  # Updated BASE_URL
LOGIN_URL = f"{BASE_URL}login.php"
USERNAME = "noah"
PASSWORD = "removed for github :)"
SCREENSHOTS_DIR = "screenshots_s4402739"

# List of pages to screenshot after login
PAGES_TO_SCREENSHOT = [
    {"name": "home_feed", "url": f"{BASE_URL}index.php"},
    {"name": "profile_own", "url": f"{BASE_URL}profile.php"},
    {"name": "friends_page", "url": f"{BASE_URL}friends.php"},
    {"name": "messages_page", "url": f"{BASE_URL}messages.php"},
    {"name": "events_page", "url": f"{BASE_URL}events.php"},
    {"name": "notifications_page", "url": f"{BASE_URL}notifications.php"},
    {"name": "customize_profile_page", "url": f"{BASE_URL}customize.php"},
    {"name": "settings_page", "url": f"{BASE_URL}settings.php"},
    {"name": "admin_dashboard", "url": f"{BASE_URL}admin/index.php"},
    {"name": "admin_users", "url": f"{BASE_URL}admin/users.php"},
    {"name": "admin_reports", "url": f"{BASE_URL}admin/reports.php"},
    {"name": "admin_analytics", "url": f"{BASE_URL}admin/analytics.php"},
]

async def take_screenshots():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            viewport={'width': 1280, 'height': 1024},
            device_scale_factor=1,
            # ignore_https_errors=True
        )
        page = await context.new_page()

        # --- Login ---
        print(f"Navigating to login page: {LOGIN_URL}")
        await page.goto(LOGIN_URL, wait_until="networkidle")

        await page.fill("input[name='username']", USERNAME)
        print(f"Filled username: {USERNAME}")
        await page.fill("input[name='password']", PASSWORD)
        print("Filled password.")

        await page.click(".login-form button[type='submit']")
        print("Clicked login button.")

        # Wait for navigation to complete (e.g., to index.php)
        # The site defaults to login.php if no session, so successful login should redirect.
        # We expect redirection to index.php.
        try:
            await page.wait_for_url(f"{BASE_URL}index.php", timeout=15000) # Increased timeout
            print(f"Successfully logged in. Current URL: {page.url}")
        except Exception as e:
            print(f"Login might have failed or redirection is different. Current URL: {page.url}")
            # Create screenshots directory if it doesn't exist (even for failure screenshot)
            if not os.path.exists(SCREENSHOTS_DIR):
                os.makedirs(SCREENSHOTS_DIR)
            await page.screenshot(path=os.path.join(SCREENSHOTS_DIR, "_login_failure.png"))
            print(f"Error during login or redirection: {e}")
            await browser.close()
            return

        if not os.path.exists(SCREENSHOTS_DIR):
            os.makedirs(SCREENSHOTS_DIR)

        # --- Take Screenshots of specified pages ---
        for page_info in PAGES_TO_SCREENSHOT:
            url = page_info["url"]
            name = page_info["name"]
            screenshot_path = os.path.join(SCREENSHOTS_DIR, f"{name}.png")

            print(f"Navigating to {name}: {url}")
            try:
                await page.goto(url, wait_until="networkidle", timeout=20000) # Increased timeout
                await page.wait_for_timeout(3000) # Increased delay for dynamic content
                await page.screenshot(path=screenshot_path, full_page=True)
                print(f"Screenshot saved: {screenshot_path}")
            except Exception as e:
                print(f"Could not take screenshot of {url}. Error: {e}")
                await page.screenshot(path=os.path.join(SCREENSHOTS_DIR, f"{name}_error.png"))

        # --- Logout ---
        LOGOUT_URL = f"{BASE_URL}logout.php"
        print(f"Navigating to logout page: {LOGOUT_URL}")
        await page.goto(LOGOUT_URL, wait_until="networkidle")
        print("Logged out.")

        await browser.close()
        print("Script finished.")

if __name__ == "__main__":
    asyncio.run(take_screenshots())