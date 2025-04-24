 // Mobile Menu Toggle
 const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
 const navMenu = document.querySelector('nav ul');
 
 mobileMenuToggle.addEventListener('click', () => {
     navMenu.classList.toggle('nav-active');
 });
 
 // Profile Tabs
 const profileTabs = document.querySelectorAll('.profile-tab');
 profileTabs.forEach(tab => {
     tab.addEventListener('click', () => {
         profileTabs.forEach(t => t.classList.remove('active'));
         tab.classList.add('active');
     });
 });
 
 // Mission Filters
 const filterButtons = document.querySelectorAll('.filter-btn');
 filterButtons.forEach(btn => {
     btn.addEventListener('click', () => {
         filterButtons.forEach(b => b.classList.remove('active'));
         btn.classList.add('active');
     });
 });