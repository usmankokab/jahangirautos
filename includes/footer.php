</div>
  </div>
</div>

<!-- Bootstrap Bundle (Popper + JS) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Advanced JavaScript Enhancements -->
<script>
// Enhanced Mobile Sidebar Toggle
function toggleSidebar() {
  const sidebar = document.getElementById('sidebarMenu');
  const overlay = document.getElementById('sidebarOverlay');
  
  if (sidebar && overlay) {
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
  } else if (sidebar) {
    sidebar.classList.toggle('show');
  }
}

// Create mobile overlay for sidebar
document.addEventListener('DOMContentLoaded', function() {
  // Create overlay for mobile sidebar
  const overlay = document.createElement('div');
  overlay.id = 'sidebarOverlay';
  overlay.className = 'position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50';
  overlay.style.zIndex = '1040';
  overlay.style.display = 'none';
  overlay.addEventListener('click', toggleSidebar);
  document.body.appendChild(overlay);
  
  // Update sidebar toggle functionality - work with Bootstrap's data attributes
  const sidebarToggle = document.querySelector('.navbar-toggler');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function(e) {
      // Let Bootstrap handle the collapse, then add our mobile overlay
      setTimeout(() => {
        const sidebar = document.getElementById('sidebarMenu');
        if (sidebar && sidebar.classList.contains('show')) {
          overlay.style.display = 'block';
          setTimeout(() => overlay.classList.add('show'), 10);
        } else {
          overlay.classList.remove('show');
          setTimeout(() => overlay.style.display = 'none', 300);
        }
      }, 10);
    });
  }
  
  // Add smooth scrolling to all anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
  
  // Button click handling
  document.querySelectorAll('button[type="submit"]').forEach(button => {
    button.addEventListener('click', function() {
      // Let the form submit naturally
    });
  });
  
  // Add fade-in animation to cards
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };
  
  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('fade-in-up');
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);
  
  // Observe all cards for animation
  document.querySelectorAll('.card').forEach(card => {
    observer.observe(card);
  });
  
  // Enhanced table interactions
  document.querySelectorAll('.table tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function() {
      this.style.transform = 'scale(1.01)';
      this.style.transition = 'all 0.2s ease';
    });
    
    row.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
    });
  });
  
  // Auto-hide alerts after 5 seconds
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-20px)';
      setTimeout(() => alert.remove(), 300);
    }, 5000);
  });
  
  // Enhanced form validation
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
      const requiredFields = this.querySelectorAll('[required]');
      let isValid = true;
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          field.classList.add('is-invalid');
          isValid = false;
        } else {
          field.classList.remove('is-invalid');
          field.classList.add('is-valid');
        }
      });
      
      if (!isValid) {
        e.preventDefault();
        // Show toast notification
        showToast('Please fill in all required fields', 'error');
      }
    });
  });
  
  // Real-time form field validation
  document.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('blur', function() {
      if (this.hasAttribute('required') && !this.value.trim()) {
        this.classList.add('is-invalid');
      } else {
        this.classList.remove('is-invalid');
        if (this.value.trim()) {
          this.classList.add('is-valid');
        }
      }
    });
    
    field.addEventListener('input', function() {
      if (this.classList.contains('is-invalid') && this.value.trim()) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
      }
    });
  });
});

// Toast notification system
function showToast(message, type = 'info') {
  const toastContainer = document.getElementById('toastContainer') || createToastContainer();
  
  const toast = document.createElement('div');
  toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'primary'} border-0`;
  toast.setAttribute('role', 'alert');
  toast.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">
        <i class="bi bi-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
        ${message}
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  `;
  
  toastContainer.appendChild(toast);
  const bsToast = new bootstrap.Toast(toast);
  bsToast.show();
  
  // Remove toast element after it's hidden
  toast.addEventListener('hidden.bs.toast', () => {
    toast.remove();
  });
}

function createToastContainer() {
  const container = document.createElement('div');
  container.id = 'toastContainer';
  container.className = 'toast-container position-fixed top-0 end-0 p-3';
  container.style.zIndex = '1055';
  document.body.appendChild(container);
  return container;
}

// Enhanced search functionality
function initializeSearch() {
  const searchInputs = document.querySelectorAll('input[type="search"], .search-input');
  
  searchInputs.forEach(input => {
    let searchTimeout;
    
    input.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      const searchTerm = this.value.toLowerCase();
      
      searchTimeout = setTimeout(() => {
        const targetTable = this.closest('.card').querySelector('table tbody');
        if (targetTable) {
          const rows = targetTable.querySelectorAll('tr');
          
          rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm) || searchTerm === '') {
              row.style.display = '';
              row.style.opacity = '1';
            } else {
              row.style.display = 'none';
              row.style.opacity = '0';
            }
          });
        }
      }, 300);
    });
  });
}

// Initialize search on page load
document.addEventListener('DOMContentLoaded', initializeSearch);

// Responsive table enhancements
function enhanceResponsiveTables() {
  document.querySelectorAll('.table-responsive').forEach(container => {
    const table = container.querySelector('table');
    if (table && window.innerWidth <= 768) {
      // Horizontal scroll indicator removed as per user request
      // Add horizontal scroll indicator
      // const scrollIndicator = document.createElement('div');
      // scrollIndicator.className = 'text-muted small text-center mt-2';
      // scrollIndicator.innerHTML = '<i class="bi bi-arrow-left-right me-1"></i>Scroll horizontally to view more';
      // container.appendChild(scrollIndicator);

      // Hide indicator when scrolled
      // container.addEventListener('scroll', function() {
      //   if (this.scrollLeft > 0) {
      //     scrollIndicator.style.opacity = '0.5';
      //   } else {
      //     scrollIndicator.style.opacity = '1';
      //   }
      // });
    }
  });
}

// Window resize handler
window.addEventListener('resize', function() {
  // Update sidebar behavior on resize
  const sidebar = document.getElementById('sidebarMenu');
  const overlay = document.getElementById('sidebarOverlay');
  
  if (window.innerWidth > 768) {
    if (sidebar) sidebar.classList.remove('show');
    if (overlay) overlay.style.display = 'none';
  }
  
  // Re-enhance responsive tables
  enhanceResponsiveTables();
});

// Initialize responsive table enhancements
document.addEventListener('DOMContentLoaded', enhanceResponsiveTables);

// Performance optimization: Lazy load images
document.addEventListener('DOMContentLoaded', function() {
  const images = document.querySelectorAll('img[data-src]');
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        img.src = img.dataset.src;
        img.classList.remove('lazy');
        imageObserver.unobserve(img);
      }
    });
  });
  
  images.forEach(img => imageObserver.observe(img));
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
  // Ctrl/Cmd + K for search
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    const searchInput = document.querySelector('input[type="search"], .search-input');
    if (searchInput) {
      searchInput.focus();
      searchInput.select();
    }
  }
  
  // Escape to close modals/dropdowns
  if (e.key === 'Escape') {
    const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
    openDropdowns.forEach(dropdown => {
      const toggle = dropdown.previousElementSibling;
      if (toggle) {
        bootstrap.Dropdown.getInstance(toggle)?.hide();
      }
    });
    
    // Close mobile sidebar
    if (window.innerWidth <= 768) {
      const sidebar = document.getElementById('sidebarMenu');
      if (sidebar && sidebar.classList.contains('show')) {
        toggleSidebar();
      }
    }
  }
});

// Add print optimization
window.addEventListener('beforeprint', function() {
  // Expand all collapsed sections for printing
  document.querySelectorAll('.collapse:not(.show)').forEach(collapse => {
    collapse.classList.add('show');
    collapse.setAttribute('data-print-expanded', 'true');
  });
});

window.addEventListener('afterprint', function() {
  // Restore collapsed sections after printing
  document.querySelectorAll('[data-print-expanded]').forEach(collapse => {
    collapse.classList.remove('show');
    collapse.removeAttribute('data-print-expanded');
  });
});

// Add connection status indicator
function updateConnectionStatus() {
  const statusIndicator = document.querySelector('.sidebar-footer .bg-success, .sidebar-footer .bg-danger');
  const statusText = document.querySelector('.sidebar-footer .text-success, .sidebar-footer .text-danger');
  
  if (navigator.onLine) {
    if (statusIndicator) {
      statusIndicator.className = statusIndicator.className.replace('bg-danger', 'bg-success');
    }
    if (statusText) {
      statusText.className = statusText.className.replace('text-danger', 'text-success');
      statusText.textContent = 'Online';
    }
  } else {
    if (statusIndicator) {
      statusIndicator.className = statusIndicator.className.replace('bg-success', 'bg-danger');
    }
    if (statusText) {
      statusText.className = statusText.className.replace('text-success', 'text-danger');
      statusText.textContent = 'Offline';
    }
  }
}

// Monitor connection status
window.addEventListener('online', updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);

// Initialize connection status
document.addEventListener('DOMContentLoaded', updateConnectionStatus);
</script>

<!-- Additional CSS for enhanced mobile experience -->
<style>
@media (max-width: 768px) {
  #sidebarMenu.show {
    left: 0 !important;
  }
  
  #sidebarOverlay.show {
    display: block !important;
  }
  
  .navbar-brand {
    font-size: 1rem;
  }
  
  .navbar-brand small {
    font-size: 0.7rem;
  }
}

/* Loading animation */
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.btn:disabled .bi-hourglass-split {
  animation: spin 1s linear infinite;
}

/* Enhanced focus states */
.form-control:focus,
.form-select:focus {
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
  border-color: #667eea;
}

/* Smooth transitions for all interactive elements */
* {
  transition: all 0.2s ease;
}

/* Enhanced scrollbar for webkit browsers */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}
</style>

</body>
</html>