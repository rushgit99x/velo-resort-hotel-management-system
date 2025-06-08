document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const content = document.querySelector('.dashboard__content');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('open');
        }
    });

    content.addEventListener('click', () => {
        if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
        }
    });
});