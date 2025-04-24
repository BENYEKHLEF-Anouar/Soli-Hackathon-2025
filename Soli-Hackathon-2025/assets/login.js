
// Tab switching functionality
const loginTab = document.querySelector('[data-tab="login"]');
const signupTab = document.querySelector('[data-tab="signup"]');
const loginForm = document.getElementById('login-form');
const signupForm = document.getElementById('signup-form');
const toggleForm = document.getElementById('toggle-form');
const footerText = document.getElementById('footer-text');

function switchToLogin() {
    loginTab.classList.add('active');
    signupTab.classList.remove('active');
    loginForm.classList.add('active');
    signupForm.classList.remove('active');
    footerText.innerHTML = 'Don\'t have an account? <a href="#" id="toggle-form">Sign up now</a>';
    toggleForm.addEventListener('click', switchToSignup);
}

function switchToSignup() {
    signupTab.classList.add('active');
    loginTab.classList.remove('active');
    signupForm.classList.add('active');
    loginForm.classList.remove('active');
    footerText.innerHTML = 'Already have an account? <a href="#" id="toggle-form">Login now</a>';
    toggleForm.addEventListener('click', switchToLogin);
}

loginTab.addEventListener('click', switchToLogin);
signupTab.addEventListener('click', switchToSignup);
toggleForm.addEventListener('click', switchToSignup);
