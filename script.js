document.addEventListener('DOMContentLoaded', function() {
    // Navbar shadow and hide/show on scroll
    const navbar = document.querySelector('.navbar');
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        // Add/remove shadow
        if (currentScroll > 0) {
            navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.3)';
        } else {
            navbar.style.boxShadow = 'none';
        }
        
        // Hide/show navbar on scroll
        if (currentScroll > lastScroll && currentScroll > 100) {
            navbar.style.transform = 'translateY(-100%)';
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScroll = currentScroll;
    });

    // Like button functionality
    const statButtons = document.querySelectorAll('.stat-button');
    statButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Visual feedback animation
            this.style.transform = 'scale(1.2)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 200);

            // Update like count if it's a heart button
            const icon = this.querySelector('i');
            if (icon.classList.contains('fa-heart')) {
                const countSpan = this.querySelector('span');
                let count = parseFloat(countSpan.textContent);
                
                if (icon.style.color !== 'red') {
                    icon.style.color = 'red';
                    count += 0.1;
                } else {
                    icon.style.color = '';
                    count -= 0.1;
                }
                countSpan.textContent = count.toFixed(1) + 'k';
            }
        });
    });

    // Post creation functionality
    const postInput = document.querySelector('.post-input');
    const createPostCard = document.querySelector('.create-post');
    const actionButtons = createPostCard.querySelectorAll('.action-button');

    postInput.addEventListener('focus', () => {
        createPostCard.style.boxShadow = '0 0 0 2px var(--accent)';
    });

    postInput.addEventListener('blur', () => {
        createPostCard.style.boxShadow = 'none';
    });

    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'translateY(-2px)';
        });

        button.addEventListener('mouseleave', () => {
            button.style.transform = 'translateY(0)';
        });
    });

    // Sidebar item hover effects
    const sidebarItems = document.querySelectorAll('.sidebar-item');
    sidebarItems.forEach(item => {
        item.addEventListener('mouseenter', () => {
            item.style.transform = 'translateX(5px)';
        });

        item.addEventListener('mouseleave', () => {
            item.style.transform = 'translateX(0)';
        });
    });

    // Add friend button functionality
    const addFriendButtons = document.querySelectorAll('.add-friend');
    addFriendButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.textContent === 'Add') {
                this.textContent = 'Added';
                this.style.color = '#10B981'; // Success green color
            } else {
                this.textContent = 'Add';
                this.style.color = 'var(--accent)';
            }
        });
    });

    // Search input enhancement
    const searchInput = document.querySelector('.search-input');
    let searchTimeout;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            // Simulate search functionality
            if (searchInput.value.length > 0) {
                searchInput.style.boxShadow = '0 0 0 2px var(--accent)';
                // Here you would typically make an API call for search results
            } else {
                searchInput.style.boxShadow = 'none';
            }
        }, 300);
    });

    // Post card hover effects
    const postCards = document.querySelectorAll('.post-card');
    postCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-5px)';
            card.style.boxShadow = '0 4px 20px rgba(0,0,0,0.2)';
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
            card.style.boxShadow = 'none';
        });
    });

    // Lazy loading for images
    const images = document.querySelectorAll('img');
    const imageOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px 50px 0px'
    };

    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
                observer.unobserve(img);
            }
        });
    }, imageOptions);

    images.forEach(img => imageObserver.observe(img));

    // Initialize tooltips for icons
    const icons = document.querySelectorAll('.nav-button i');
    icons.forEach(icon => {
        icon.setAttribute('title', icon.className.split('-')[1]);
    });
});