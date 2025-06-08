function validateRegisterForm() {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const name = document.getElementById('name').value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!name.trim()) {
        alert('Name is required.');
        return false;
    }
    if (!emailRegex.test(email)) {
        alert('Invalid email format.');
        return false;
    }
    if (password.length < 6) {
        alert('Password must be at least 6 characters long.');
        return false;
    }
    return true;
}

function validateLoginForm() {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailRegex.test(email)) {
        alert('Invalid email format.');
        return false;
    }
    if (!password) {
        alert('Password is required.');
        return false;
    }
    return true;
}