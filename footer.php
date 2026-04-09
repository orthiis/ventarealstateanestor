</div> <!-- Close main-content -->
</div> <!-- Close d-flex -->

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="javascript.js"></script>

<script>
// Mobile sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show-mobile');
});

// Close sidebar on mobile when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    
    if (window.innerWidth < 992) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('show-mobile');
        }
    }
});

// Load notifications
function loadNotifications() {
    fetch('ajax/get-notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                document.getElementById('notificationCount').textContent = data.count;
            }
        });
}

// Load on page load
loadNotifications();
setInterval(loadNotifications, 60000); // Check every minute
</script>

</body>
</html>