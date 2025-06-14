document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    const loginForm = document.querySelector('#login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            const email = loginForm.querySelector('input[type="email"]').value;
            const password = loginForm.querySelector('input[type="password"]').value;
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
            }
        });
    }

    const registerForm = document.querySelector('#register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            const password = registerForm.querySelector('#password').value;
            const confirmPassword = registerForm.querySelector('#confirm-password').value;
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    }
});