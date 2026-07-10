// Modern Clinic Management System JavaScript

// Global Variables
let currentUser = null;
let notifications = [];

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    checkSession();
    loadNotifications();
});

// Initialize Application
function initializeApp() {
    console.log('Clinic Management System Initialized');
    
    // Check if we're on mobile and adjust sidebar
    if (window.innerWidth <= 768) {
        toggleSidebar(false);
    }
    
    // Initialize tooltips
    initializeTooltips();
    
    // Load dashboard data if on dashboard
    if (document.getElementById('dashboard-stats')) {
        loadDashboardData();
    }
    
    // Initialize date pickers
    initializeDatePickers();
}

// Setup Event Listeners
function setupEventListeners() {
    // Mobile menu toggle
    const menuToggle = document.getElementById('menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', toggleSidebar);
    }
    
    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    // Appointment booking form
    const bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', handleBooking);
    }
    
    // Doctor selection
    const doctorCards = document.querySelectorAll('.doctor-card');
    doctorCards.forEach(card => {
        card.addEventListener('click', function() {
            selectDoctor(this.dataset.doctorId);
        });
    });
    
    // Time slot selection
    const timeSlots = document.querySelectorAll('.time-slot');
    timeSlots.forEach(slot => {
        slot.addEventListener('click', function() {
            if (!this.classList.contains('booked')) {
                selectTimeSlot(this.dataset.slotId);
            }
        });
    });
    
    // Calendar day selection
    const calendarDays = document.querySelectorAll('.calendar-day');
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            selectDate(this.dataset.date);
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
    
    // Real-time search
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
}

// Check User Session
function checkSession() {
    fetch('api/check-session.php')
        .then(response => response.json())
        .then(data => {
            if (data.loggedIn) {
                currentUser = data.user;
                updateUIForUser(data.user);
            }
        })
        .catch(error => console.error('Session check failed:', error));
}

// Load Notifications
function loadNotifications() {
    if (!currentUser) return;
    
    fetch(`api/notifications.php?user_id=${currentUser.id}`)
        .then(response => response.json())
        .then(data => {
            notifications = data;
            updateNotificationBadge(notifications.filter(n => !n.is_read).length);
        })
        .catch(error => console.error('Failed to load notifications:', error));
}

// Toggle Sidebar
function toggleSidebar(show) {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (show === undefined) {
        show = sidebar.style.transform === 'translateX(0px)';
    }
    
    if (show) {
        sidebar.style.transform = 'translateX(0)';
        mainContent.style.marginLeft = '250px';
    } else {
        sidebar.style.transform = 'translateX(-100%)';
        mainContent.style.marginLeft = '0';
    }
}

// Handle Booking Form Submission
function handleBooking(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    
    // Validate form
    if (!validateBookingData(data)) {
        showNotification('Please fill all required fields', 'error');
        return;
    }
    
    // Check availability
    checkAvailability(data)
        .then(available => {
            if (available) {
                return submitBooking(data);
            } else {
                showNotification('Selected time slot is no longer available', 'error');
            }
        })
        .then(result => {
            if (result.success) {
                showNotification('Appointment booked successfully!', 'success');
                setTimeout(() => {
                    window.location.href = 'patient/my-appointments.php';
                }, 2000);
            } else {
                showNotification(result.message || 'Booking failed', 'error');
            }
        })
        .catch(error => {
            console.error('Booking error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
}

// Check Appointment Availability
async function checkAvailability(data) {
    const params = new URLSearchParams({
        doctor_id: data.doctor_id,
        date: data.appointment_date,
        time_slot: data.time_slot
    });
    
    const response = await fetch(`api/check-availability.php?${params}`);
    const result = await response.json();
    return result.available;
}

// Submit Booking
async function submitBooking(data) {
    const response = await fetch('api/book-appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    });
    
    return await response.json();
}

// Select Doctor
function selectDoctor(doctorId) {
    // Remove selected class from all doctor cards
    document.querySelectorAll('.doctor-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to clicked doctor
    const selectedCard = document.querySelector(`.doctor-card[data-doctor-id="${doctorId}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Load doctor's availability
    loadDoctorAvailability(doctorId);
    
    // Update hidden input
    const doctorInput = document.getElementById('selected-doctor');
    if (doctorInput) {
        doctorInput.value = doctorId;
    }
}

// Load Doctor Availability
function loadDoctorAvailability(doctorId) {
    const date = document.getElementById('appointment-date').value;
    if (!date) return;
    
    fetch(`api/doctor-availability.php?doctor_id=${doctorId}&date=${date}`)
        .then(response => response.json())
        .then(data => {
            updateTimeSlots(data.available_slots, data.booked_slots);
        })
        .catch(error => console.error('Failed to load availability:', error));
}

// Update Time Slots
function updateTimeSlots(availableSlots, bookedSlots) {
    const slotsContainer = document.querySelector('.time-slots');
    if (!slotsContainer) return;
    
    // Clear existing slots
    slotsContainer.innerHTML = '';
    
    // Create new slots
    availableSlots.forEach(slot => {
        const slotElement = document.createElement('div');
        slotElement.className = `time-slot ${bookedSlots.includes(slot.id) ? 'booked' : ''}`;
        slotElement.dataset.slotId = slot.id;
        slotElement.textContent = slot.time;
        
        if (!bookedSlots.includes(slot.id)) {
            slotElement.addEventListener('click', function() {
                selectTimeSlot(slot.id);
            });
        }
        
        slotsContainer.appendChild(slotElement);
    });
}

// Select Time Slot
function selectTimeSlot(slotId) {
    // Remove selected class from all slots
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('selected');
    });
    
    // Add selected class to clicked slot
    const selectedSlot = document.querySelector(`.time-slot[data-slot-id="${slotId}"]`);
    if (selectedSlot && !selectedSlot.classList.contains('booked')) {
        selectedSlot.classList.add('selected');
    }
    
    // Update hidden input
    const slotInput = document.getElementById('selected-time-slot');
    if (slotInput) {
        slotInput.value = slotId;
    }
}

// Select Date
function selectDate(date) {
    // Remove selected class from all days
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.classList.remove('selected');
    });
    
    // Add selected class to clicked day
    const selectedDay = document.querySelector(`.calendar-day[data-date="${date}"]`);
    if (selectedDay) {
        selectedDay.classList.add('selected');
    }
    
    // Update date input
    const dateInput = document.getElementById('appointment-date');
    if (dateInput) {
        dateInput.value = date;
    }
    
    // Reload availability if doctor is selected
    const selectedDoctor = document.getElementById('selected-doctor');
    if (selectedDoctor && selectedDoctor.value) {
        loadDoctorAvailability(selectedDoctor.value);
    }
}

// Validate Booking Data
function validateBookingData(data) {
    const required = ['patient_id', 'doctor_id', 'appointment_date', 'time_slot', 'reason_for_visit'];
    return required.every(field => data[field] && data[field].trim() !== '');
}

// Validate Form
function validateForm(event) {
    const form = event.target;
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
            
            // Show error message
            const errorDiv = input.nextElementSibling;
            if (errorDiv && errorDiv.classList.contains('error-message')) {
                errorDiv.textContent = `${input.name} is required`;
            }
        } else {
            input.classList.remove('error');
        }
    });
    
    if (!isValid) {
        event.preventDefault();
        showNotification('Please fill all required fields', 'error');
    }
}

// Handle Search
function handleSearch(event) {
    const query = event.target.value;
    const searchType = document.getElementById('search-type').value;
    
    if (query.length < 2) return;
    
    fetch(`api/search.php?type=${searchType}&q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            updateSearchResults(data);
        })
        .catch(error => console.error('Search failed:', error));
}

// Update Search Results
function updateSearchResults(results) {
    const resultsContainer = document.getElementById('search-results');
    if (!resultsContainer) return;
    
    resultsContainer.innerHTML = '';
    
    if (results.length === 0) {
        resultsContainer.innerHTML = '<p class="no-results">No results found</p>';
        return;
    }
    
    results.forEach(result => {
        const resultElement = createSearchResultElement(result);
        resultsContainer.appendChild(resultElement);
    });
}

// Show Notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="notification-icon"></i>
            <span class="notification-message">${message}</span>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
    
    // Close button
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.remove();
    });
}

// Update Notification Badge
function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Update UI for Logged In User
function updateUIForUser(user) {
    // Update user info in header
    const userInfo = document.querySelector('.user-info');
    if (userInfo) {
        userInfo.innerHTML = `
            <span class="user-name">${user.full_name}</span>
            <div class="user-avatar">${user.full_name.charAt(0)}</div>
        `;
    }
    
    // Show/hide role-specific elements
    document.querySelectorAll('[data-role]').forEach(element => {
        const requiredRole = element.dataset.role;
        if (requiredRole === user.user_type || requiredRole === 'all') {
            element.style.display = 'block';
        } else {
            element.style.display = 'none';
        }
    });
}

// Handle Logout
function handleLogout(event) {
    event.preventDefault();
    
    fetch('logout.php')
        .then(() => {
            window.location.href = 'login.php';
        })
        .catch(error => {
            console.error('Logout failed:', error);
            window.location.href = 'login.php';
        });
}

// Load Dashboard Data
function loadDashboardData() {
    const userType = currentUser?.user_type;
    
    fetch(`api/dashboard-data.php?type=${userType}`)
        .then(response => response.json())
        .then(data => {
            updateDashboardStats(data.stats);
            updateRecentAppointments(data.recent_appointments);
            updateNotifications(data.notifications);
        })
        .catch(error => console.error('Failed to load dashboard data:', error));
}

// Update Dashboard Stats
function updateDashboardStats(stats) {
    for (const [key, value] of Object.entries(stats)) {
        const element = document.getElementById(`stat-${key}`);
        if (element) {
            element.textContent = value;
        }
    }
}

// Update Recent Appointments
function updateRecentAppointments(appointments) {
    const container = document.getElementById('recent-appointments');
    if (!container) return;
    
    let html = '';
    appointments.forEach(appointment => {
        html += `
            <tr>
                <td>${appointment.patient_name}</td>
                <td>${appointment.doctor_name}</td>
                <td>${appointment.date}</td>
                <td>${appointment.time}</td>
                <td><span class="badge badge-${appointment.status}">${appointment.status}</span></td>
                <td>
                    <button onclick="viewAppointment(${appointment.id})" class="btn btn-sm btn-primary">View</button>
                </td>
            </tr>
        `;
    });
    
    container.innerHTML = html;
}

// Initialize Tooltips
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show Tooltip
function showTooltip(event) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = event.target.dataset.tooltip;
    
    const rect = event.target.getBoundingClientRect();
    tooltip.style.top = `${rect.top - 30}px`;
    tooltip.style.left = `${rect.left + (rect.width / 2)}px`;
    
    document.body.appendChild(tooltip);
    
    event.target.addEventListener('mouseleave', () => {
        tooltip.remove();
    });
}

// Hide Tooltip
function hideTooltip(event) {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Initialize Date Pickers
function initializeDatePickers() {
    const datePickers = document.querySelectorAll('.date-picker');
    datePickers.forEach(picker => {
        picker.addEventListener('click', function() {
            showDatePicker(this);
        });
    });
}

// Show Date Picker
function showDatePicker(input) {
    // Implementation for custom date picker
    // Could use a library like flatpickr or create custom
}

// Debounce Function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Format Date
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Format Time
function formatTime(time) {
    return new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Export to CSV
function exportToCSV(data, filename) {
    const csv = data.map(row => 
        Object.values(row).map(value => 
            typeof value === 'string' ? `"${value}"` : value
        ).join(',')
    ).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename}.csv`;
    a.click();
}

// Print Page
function printPage() {
    window.print();
}