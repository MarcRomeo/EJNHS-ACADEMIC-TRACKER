// Configuration
const API_BASE = './api';
let currentUser = null;
let currentRole = null;

// DOM Elements
const homeSection = document.getElementById('home');
const dashboardSection = document.getElementById('dashboard');
const openLoginTypeBtn = document.getElementById('openLoginTypeBtn');
const signupWithGoogleBtn = document.getElementById('signupWithGoogleBtn');
const loginTypeModal = document.getElementById('loginTypeModal');
const closeLoginTypeModal = document.getElementById('closeLoginTypeModal');
const loginAsParent = document.getElementById('loginAsParent');
const loginAsAdmin = document.getElementById('loginAsAdmin');
const adminModal = document.getElementById('adminModal');
const closeAdminModal = document.getElementById('closeAdminModal');
const adminLoginForm = document.getElementById('adminLoginForm');
const adminLoginError = document.getElementById('adminLoginError');
const parentLinkModal = document.getElementById('parentLinkModal');
const closeParentLinkModal = document.getElementById('closeParentLinkModal');
const addChildLinkBtn = document.getElementById('addChildLinkBtn');
const submitChildLinksBtn = document.getElementById('submitChildLinksBtn');
const dashboardContent = document.getElementById('dashboardContent');
const dashActions = document.getElementById('dashActions');

// Utility Functions
function showNotif(message) {
    const notif = document.createElement('div');
    notif.className = 'notif';
    notif.textContent = message;
    document.body.appendChild(notif);
    setTimeout(() => notif.remove(), 3000);
}

function getSubjectFinalNumber(subject) {
    if (!subject || typeof subject !== 'object') return null;
    const raw = subject.final ?? subject.grade;
    const num = parseFloat(raw);
    return Number.isFinite(num) ? num : null;
}

// DepEd-style: General Average = (sum of subject final grades) / (total number of subjects)
function computeGeneralAverage(subjects) {
    const list = Array.isArray(subjects) ? subjects : [];
    const finals = list.map(getSubjectFinalNumber).filter(v => v !== null);
    if (finals.length === 0) return null;
    return finals.reduce((a, b) => a + b, 0) / finals.length;
}

function formatGeneralAverage(avg, { decimals = 0 } = {}) {
    if (avg === null) return 'N/A';
    if (decimals === 0) return String(Math.round(avg));
    return avg.toFixed(decimals);
}

function showSection(section) {
    homeSection.style.display = 'none';
    dashboardSection.style.display = 'none';
    
    if (section === 'home') {
        homeSection.style.display = 'block';
    } else if (section === 'dashboard') {
        dashboardSection.style.display = 'block';
    }
}

function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
    } catch (e) {
        console.error('Copy failed:', e);
    }
    document.body.removeChild(textarea);
}

// Modal Functions
function showLoginTypeModal() {
    loginTypeModal.classList.add('active');
}

function hideLoginTypeModal() {
    loginTypeModal.classList.remove('active');
}

function showAdminModal() {
    adminModal.classList.add('active');
}

function hideAdminModal() {
    adminModal.classList.remove('active');
}

function showParentSignupModal() {
    parentLinkModal.classList.add('active');
}

function hideParentSignupModal() {
    parentLinkModal.classList.remove('active');
}

// Event Listeners
if (openLoginTypeBtn) {
    openLoginTypeBtn.addEventListener('click', showLoginTypeModal);
}

if (signupWithGoogleBtn) {
    // Redirect parents directly to the Parent Portal for signup/login
    signupWithGoogleBtn.addEventListener('click', () => {
        // Parent Signup -> show signup form by default
        window.location.href = 'parent-portal.php';
    });
}

if (closeLoginTypeModal) {
    closeLoginTypeModal.addEventListener('click', hideLoginTypeModal);
}

if (closeAdminModal) {
    closeAdminModal.addEventListener('click', hideAdminModal);
}

if (closeParentLinkModal) {
    closeParentLinkModal.addEventListener('click', hideParentSignupModal);
}

if (loginAsParent) {
    loginAsParent.addEventListener('click', () => {
        hideLoginTypeModal();
        // Parent Sign In -> go directly to login form
        window.location.href = 'parent-portal.php?showLogin=1';
    });
}

if (loginAsAdmin) {
    loginAsAdmin.addEventListener('click', () => {
        hideLoginTypeModal();
        showAdminModal();
    });
}

// Add Child Link Row
if (addChildLinkBtn) {
    addChildLinkBtn.addEventListener('click', () => {
        const childLinkInputs = document.getElementById('childLinkInputs');
        const newRow = document.createElement('div');
        newRow.className = 'child-link-row';
        newRow.innerHTML = `
            <input type="text" class="childLinkInput" placeholder="Paste another child code..." />
            <button class="remove-child-link" style="background: #f44336; padding: 0.5rem; border-radius: 8px; border: none; color: white; cursor: pointer;">Remove</button>
        `;
        childLinkInputs.appendChild(newRow);
        
        // Add remove functionality
        newRow.querySelector('.remove-child-link').addEventListener('click', () => {
            newRow.remove();
        });
    });
}

// Submit Parent Signup with Child Links
if (submitChildLinksBtn) {
    submitChildLinksBtn.addEventListener('click', async () => {
        const parentEmail = document.getElementById('parentEmail').value.trim();
        const parentFullName = document.getElementById('parentFullName').value.trim();
        const signupCode = document.getElementById('signupCode').value.trim();
        const childInputs = document.querySelectorAll('.childLinkInput');
        
        const childCodes = Array.from(childInputs)
            .map(input => input.value.trim())
            .filter(code => code.length > 0);
        
        // Validation
        if (!parentEmail || !parentFullName || !signupCode) {
            document.getElementById('signupError').textContent = 'Please fill in all required fields';
            document.getElementById('signupError').style.display = 'block';
            return;
        }
        
        if (childCodes.length === 0) {
            document.getElementById('signupError').textContent = 'Please enter at least one child code';
            document.getElementById('signupError').style.display = 'block';
            return;
        }
        
        try {
            const response = await fetch(`${API_BASE}/parent-auth.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'verifySignupCode',
                    email: parentEmail,
                    fullName: parentFullName,
                    signupCode: signupCode,
                    childCodes: childCodes
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotif('Signup successful! You are now linked to your child.');
                hideParentSignupModal();
                
                // Log in the parent
                currentUser = result.parent;
                currentRole = 'parent';
                renderParentDashboard();
            } else {
                document.getElementById('signupError').textContent = result.error;
                document.getElementById('signupError').style.display = 'block';
            }
        } catch (error) {
            console.error('Signup error:', error);
            document.getElementById('signupError').textContent = 'An error occurred. Please try again.';
            document.getElementById('signupError').style.display = 'block';
        }
    });
}

// Admin Login
if (adminLoginForm) {
    adminLoginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const username = document.getElementById('adminUsername').value.trim();
        const password = document.getElementById('adminPassword').value;
        
        try {
            const response = await fetch(`${API_BASE}/admin.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'login',
                    username: username,
                    password: password
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                currentUser = result.admin;
                currentRole = 'admin';
                hideAdminModal();
                showNotif('Login successful!');
                renderAdminDashboard();
            } else {
                adminLoginError.textContent = result.error;
            }
        } catch (error) {
            console.error('Login error:', error);
            adminLoginError.textContent = 'An error occurred. Please try again.';
        }
    });
}

// Render Parent Dashboard
async function renderParentDashboard() {
    showSection('dashboard');
    
    document.getElementById('dashboardTitle').textContent = `Welcome, ${currentUser.name}`;
    
    // Show logout button
    dashActions.innerHTML = `
        <button onclick="logout()" class="btn btn-secondary">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    `;
    
    dashboardContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><p>Loading your child\'s grades...</p></div>';
    
    try {
        // Fetch grades for each linked child
        let gradesHTML = '';
        
        for (const childCode of currentUser.linkedChildren) {
            const response = await fetch(`${API_BASE}/parent-auth.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'getChildGrades',
                    parentEmail: currentUser.email,
                    childCode: childCode
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const student = result.student;
                const generalAvg = computeGeneralAverage(student.subjects);
                const generalAvgText = formatGeneralAverage(generalAvg, { decimals: 0 });
                gradesHTML += `
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <h3 style="color: #82b2ff; margin-bottom: 1rem;">
                            <i class="fas fa-user-graduate"></i> ${student.name}
                        </h3>
                        <p style="margin-bottom: 0.5rem;"><strong>Grade:</strong> ${student.grade}</p>
                        <p style="margin-bottom: 1rem;"><strong>Section:</strong> ${student.section}</p>

                        <p style="margin-bottom: 1rem;"><strong>General Average:</strong> <span style="color:#4CAF50; font-weight:700;">${generalAvgText}</span></p>
                        
                        ${Array.isArray(student.subjects) && student.subjects.length > 0 ? `
                            <h4 style="color: #a5d6a7; margin-bottom: 0.5rem;">Subjects & Grades:</h4>
                            <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 8px;">
                                ${student.subjects.map(sub => `
                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <span>${sub.title}</span>
                                        <span style="color: #4CAF50; font-weight: bold;">${sub.final ?? sub.grade}</span>
                                    </div>
                                `).join('')}
                            </div>
                        ` : '<p style="color: #999;">No grades available yet.</p>'}
                    </div>
                `;
            }
        }
        
        if (gradesHTML) {
            dashboardContent.innerHTML = gradesHTML;
        } else {
            dashboardContent.innerHTML = '<p style="text-align: center; color: #999;">No student data found.</p>';
        }
    } catch (error) {
        console.error('Error fetching grades:', error);
        dashboardContent.innerHTML = '<p style="text-align: center; color: #f44336;">Error loading grades. Please try again.</p>';
    }
}

// Render Admin Dashboard
async function renderAdminDashboard() {
    showSection('dashboard');
    
    document.getElementById('dashboardTitle').textContent = `Grade Management System`;
    
    // Simple logout button only
    dashActions.innerHTML = `
        <button onclick="logout()" class="btn btn-secondary" style="padding: 0.6rem 1.2rem;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    `;
    
    dashboardContent.innerHTML = `
        <style>
            .grade-card {
                background: #fff;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .grade-card h3 {
                color: #2563eb;
                margin: 0 0 15px 0;
                font-size: 1.3rem;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                display: block;
                color: #374151;
                font-weight: 600;
                margin-bottom: 5px;
                font-size: 0.95rem;
            }
            .form-group input,
            .form-group select {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 1rem;
                background: #f9fafb;
                color: #111827;
            }
            .form-group input:focus,
            .form-group select:focus {
                outline: none;
                border-color: #2563eb;
                background: #fff;
            }
            .grade-inputs {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            .submit-btn {
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 1.05rem;
                font-weight: 600;
                cursor: pointer;
                width: 100%;
                margin-top: 10px;
                transition: transform 0.2s;
            }
            .submit-btn:hover {
                transform: scale(1.02);
                background: linear-gradient(135deg, #1d4ed8, #1e40af);
            }
            .students-list {
                max-height: 400px;
                overflow-y: auto;
            }
            .student-item {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                padding: 12px 15px;
                margin-bottom: 10px;
                border-radius: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                transition: background 0.2s;
            }
            .student-item:hover {
                background: #f3f4f6;
                border-color: #2563eb;
            }
            .student-info {
                flex: 1;
            }
            .student-name {
                font-weight: 600;
                color: #111827;
                margin-bottom: 4px;
            }
            .student-meta {
                font-size: 0.85rem;
                color: #6b7280;
            }
            .student-actions {
                display: flex;
                gap: 8px;
            }
            .edit-btn {
                background: #2563eb;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.9rem;
            }
            .delete-btn {
                background: #ef4444;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 6px;
                cursor: pointer;
            }
            .section-header {
                color: #6b7280;
                font-size: 0.95rem;
                font-weight: 600;
                margin: 20px 0 10px 0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
        </style>
        
        <!-- Add Student Form -->
        <div class="grade-card">
            <h3><i class="fas fa-user-plus"></i> Add Student</h3>
            
            <div class="form-group">
                <label>Student Full Name *</label>
                <input type="text" id="studentName" placeholder="Enter student full name">
            </div>
            
            <div class="form-group">
                <label>Grade Level *</label>
                <select id="studentGrade">
                    <option value="">Select Grade</option>
                    <option value="Grade 11">Grade 11</option>
                    <option value="Grade 12">Grade 12</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Track *</label>
                <select id="studentStrand">
                    <option value="">Select Track</option>
                    <option value="Academic Track">Academic Track</option>
                    <option value="Techpro Track">Techpro Track</option>
                </select>
            </div>

            <div class="form-group">
                <label>Section *</label>
                <input type="text" id="studentSection" placeholder="e.g., CCS-A">
            </div>
            
            <button id="addStudentBtn" class="submit-btn">
                <i class="fas fa-user-plus"></i> Add Student
            </button>
        </div>
        
        <!-- Students List -->
        <div class="grade-card">
            <h3><i class="fas fa-users"></i> Published Students</h3>
            <div id="studentsList" class="students-list">
                <p style="text-align: center; color: #9ca3af; padding: 20px;">
                    Loading students...
                </p>
            </div>
        </div>
    `;

    // Wire up Add Student button
    const addStudentBtn = document.getElementById('addStudentBtn');
    if (addStudentBtn) {
        addStudentBtn.addEventListener('click', addStudentRecord);
    }
    
    // Load existing students
    loadStudentsList();
}

// Add a student record (no grades yet)
async function addStudentRecord() {
    const name = document.getElementById('studentName').value.trim();
    const grade = document.getElementById('studentGrade').value;
    const track = document.getElementById('studentStrand').value;
    const section = document.getElementById('studentSection').value.trim();

    if (!name || !grade || !track || !section) {
        alert('Please fill in student name, grade level, track, and section');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'addStudent',
                name: name,
                grade: grade,
                section: section,
                track: track,
                adminUsername: currentUser.username
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotif(`Student added! Child Code: ${result.student.childCode}`);
            showSimpleChildCodeModal(result.student);

            document.getElementById('studentName').value = '';
            document.getElementById('studentGrade').value = '';
            document.getElementById('studentStrand').value = '';
            document.getElementById('studentSection').value = '';

            // Refresh list and immediately open the Subjects box
            await loadStudentsList();
            if (result.student.id) {
                openSubjectsModal(String(result.student.id));
            }
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error adding student:', error);
        alert('An error occurred while adding the student');
    }
}

// Load students list
async function loadStudentsList() {
    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getStudents' })
        });
        
        const result = await response.json();
        const listEl = document.getElementById('studentsList');

        if (result.success && result.students.length > 0) {
            listEl.innerHTML = result.students.map(student => {
                const avg = computeGeneralAverage(student.subjects);
                const finalGrade = formatGeneralAverage(avg, { decimals: 0 });

                return `
                    <div class="student-item">
                        <div class="student-info">
                            <div class="student-name">${student.name}</div>
                            <div class="student-meta">
                                ${student.grade} - ${student.section} | 
                                General Average: <strong>${finalGrade}</strong> |
                                Code: <code>${student.childCode}</code>
                            </div>
                        </div>
                        <div class="student-actions">
                            <button class="edit-btn" onclick="openSubjectsModal('${student.id}')">
                                <i class="fas fa-book"></i> Subjects
                            </button>
                            <button class="delete-btn" onclick="deleteStudent('${student.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            listEl.innerHTML = `
                <p style="text-align: center; color: #9ca3af; padding: 20px;">
                    <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                    No students published yet
                </p>
            `;
        }
    } catch (error) {
        console.error('Error loading students:', error);
        document.getElementById('studentsList').innerHTML = `
            <p style="text-align: center; color: #ef4444; padding: 20px;">Error loading students</p>
        `;
    }
}

// Show simple child code modal
function showSimpleChildCodeModal(student) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 560px; background:#ffffff; color:#111827; text-align:left; backdrop-filter:none;">
            <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            <h2 style="color: #2563eb; margin-bottom: 1rem;">
                <i class="fas fa-check-circle"></i> Student Added!
            </h2>
            <div style="background:#f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb; margin: 1rem 0;">
                <div style="display:grid; gap:6px;">
                    <div style="color:#374151;"><strong>Student:</strong> ${escapeHtml(student.name)}</div>
                    <div style="color:#374151;"><strong>Grade:</strong> ${escapeHtml(student.grade)} - ${escapeHtml(student.section)}</div>
                </div>

                <div style="margin-top: 12px;">
                    <div style="margin-bottom:6px; color:#374151; font-weight:700;">Child Code</div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input
                            type="text"
                            value="${escapeHtml(student.childCode)}"
                            readonly
                            style="flex:1; width:100%; padding: 12px 12px; border-radius: 10px; border: 2px solid #2563eb; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 1.05rem; letter-spacing: 1px; color:#1d4ed8; background:#ffffff;"
                            onclick="this.select()"
                        />
                        <button
                            type="button"
                            onclick="copyToClipboard('${student.childCode}'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 1200)"
                            style="white-space:nowrap; background: #10b981; color: white; border: none; padding: 12px 14px; border-radius: 10px; cursor: pointer; font-weight:700;"
                        >Copy</button>
                    </div>
                    <div style="margin-top: 8px; font-size: 0.9rem; color: #4b5563; line-height:1.4;">
                        Share this code with the parent so they can link their account. Then add the student's subjects and grade components.
                    </div>
                </div>
            </div>

            <div style="display:grid; gap:10px;">
                <button type="button" onclick="openSubjectsModal('${student.id}'); this.closest('.modal').remove()"
                    style="background: #2563eb; color: white; border: none; padding: 12px 16px; border-radius: 10px; cursor: pointer; width: 100%; font-weight:700;">
                    <i class="fas fa-book"></i> Add Subjects & Grades
                </button>
                <button type="button" onclick="this.closest('.modal').remove()"
                    style="background: #6b7280; color: white; border: none; padding: 12px 16px; border-radius: 10px; cursor: pointer; width: 100%; font-weight:700;">
                    Close
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Open modal to manage subjects and their grade components for a student
async function openSubjectsModal(studentId) {
    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getStudents' })
        });

        const result = await response.json();
        const student = result.students.find(s => String(s.id) === String(studentId));

        if (!student) {
            alert('Student not found');
            return;
        }

        const subjects = student.subjects || [];
        const generalAvg = computeGeneralAverage(subjects);
        const generalAvgText = formatGeneralAverage(generalAvg, { decimals: 0 });

        const modal = document.createElement('div');
        modal.className = 'modal active';
        modal.innerHTML = `
            <div class="modal-content" style="width: 94vw; max-width: 980px; max-height: 80vh; overflow-y: auto; background:#ffffff; color:#111827; text-align:left;">
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                <h2 style="color: #2563eb; margin-bottom: 1rem;">
                    <i class="fas fa-book"></i> Subjects - ${student.name}
                </h2>

                <div style="background:#f9fafb; border:1px solid #e5e7eb; padding:12px; border-radius:12px; margin-bottom: 1rem;">
                    <p style="color:#111827; margin:0; font-size:0.95rem; line-height:1.45;">
                        Add each subject and its <b>Activities + Quizzes</b> (this equals <b>Written Works</b>), <b>Performance Tasks</b>, and <b>Quarterly Assessment</b>.
                        The final grade is calculated automatically.
                    </p>
                </div>

                <div style="margin-bottom:1.5rem;">
                    <h3 style="font-size:1rem; color:#374151; margin-bottom:0.5rem;">Current Subjects</h3>
                    ${subjects.length > 0 ? `
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; background:#ecfdf5; border:1px solid #a7f3d0; padding:10px 12px; border-radius:10px; margin-bottom:10px;">
                            <div style="color:#065f46; font-weight:700;">General Average (DepEd):</div>
                            <div style="color:#065f46; font-weight:800; font-size:1.05rem;">${generalAvgText}</div>
                        </div>
                    ` : ''}
                    ${subjects.length === 0 ? `
                        <p style="color:#9ca3af;">No subjects yet. Use the form below to add one.</p>
                    ` : `
                        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                            <thead>
                                <tr style="background:#2563eb; color:#ffffff;">
                                    <th style="padding:8px; text-align:left;">Subject</th>
                                    <th style="padding:8px; text-align:center;">Quarter</th>
                                    <th style="padding:8px; text-align:center;">Written (A+Q) (30%)</th>
                                    <th style="padding:8px; text-align:center;">Performance (50%)</th>
                                    <th style="padding:8px; text-align:center;">Quarterly (20%)</th>
                                    <th style="padding:8px; text-align:center;">Final</th>
                                    <th style="padding:8px; text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${subjects.map((sub, idx) => {
                                    const written = formatWrittenWorksScore(sub);
                                    const performance = formatPerformanceTasksScore(sub);
                                    const quarterly = sub.quarterly ?? '';
                                    const final = sub.final ?? sub.grade ?? '';
                                    const quarterLabel = sub.quarterLabel ?? sub.quarter ?? '';
                                    return `
                                        <tr style="border-bottom:1px solid #e5e7eb; background:#ffffff; color:#111827;">
                                            <td style="padding:8px;">${sub.title || 'Subject ' + (idx+1)}</td>
                                            <td style="padding:8px; text-align:center;">${quarterLabel || '—'}</td>
                                            <td style="padding:8px; text-align:center;">${written}</td>
                                            <td style="padding:8px; text-align:center;">${performance}</td>
                                            <td style="padding:8px; text-align:center;">${quarterly}</td>
                                            <td style="padding:8px; text-align:center; font-weight:600;">${final}</td>
                                            <td style="padding:8px; text-align:center;">
                                                <button style="background:#2563eb; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.8rem; margin-right:4px;" onclick="editSubject('${student.id}', ${idx})">Edit</button>
                                                <button style="background:#ef4444; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.8rem;" onclick="deleteSubject('${student.id}', ${idx})">Delete</button>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                            <tfoot>
                                <tr style="background:#f3f4f6; border-top:2px solid #e5e7eb; color:#111827;">
                                    <td colspan="5" style="padding:10px 8px; text-align:right; font-weight:800;">General Average</td>
                                    <td style="padding:10px 8px; text-align:center; font-weight:900; color:#065f46;">${generalAvgText}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    `}
                </div>

                <div style="border-top:1px solid #e5e7eb; padding-top:1rem;">
                    <h3 style="font-size:1rem; color:#374151; margin-bottom:0.5rem;">Add New Subject</h3>
                    <div class="grade-inputs">
                        <div class="form-group">
                            <label>Subject Name *</label>
                            <input type="text" id="newSubjectName" placeholder="e.g., Mathematics">
                        </div>
                        <div class="form-group">
                            <label>Quarter *</label>
                            <select id="newQuarterLabel">
                                <option value="">Select Quarter</option>
                                <option value="1st Quarter">1st Quarter</option>
                                <option value="2nd Quarter">2nd Quarter</option>
                                <option value="3rd Quarter">3rd Quarter</option>
                                <option value="4th Quarter">4th Quarter</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Written Works (30%) = Activities + Quizzes *</label>

                            <div class="ww-grid" id="newWwGrid">
                                <div class="ww-panel">
                                    <div class="ww-panel-head">
                                        <div class="ww-panel-title"><i class="fas fa-clipboard-check"></i> Activities</div>
                                        <button type="button" class="small-btn" id="addNewActivityBtn"><i class="fas fa-plus"></i> Add</button>
                                    </div>
                                    <div class="ww-rows" id="newActivityRows"></div>
                                    <div class="ww-summary">Percentage Score: <b id="newActivityAvg">—</b></div>
                                </div>

                                <div class="ww-panel">
                                    <div class="ww-panel-head">
                                        <div class="ww-panel-title"><i class="fas fa-pen"></i> Quizzes</div>
                                        <button type="button" class="small-btn" id="addNewQuizBtn"><i class="fas fa-plus"></i> Add</button>
                                    </div>
                                    <div class="ww-rows" id="newQuizRows"></div>
                                    <div class="ww-summary">Percentage Score: <b id="newQuizAvg">—</b></div>
                                </div>
                            </div>

                            <div class="ww-summary" style="margin-top:10px;">Combined (Activities + Quizzes): <b id="newWrittenCombined">—</b></div>
                            <input type="number" id="newWritten" min="0" max="100" placeholder="Auto-calculated" readonly style="margin-top:10px;">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Performance Tasks (50%) (PETA) *</label>
                            <div class="ww-grid" style="margin-top:8px;">
                                <div class="ww-panel">
                                    <div class="ww-panel-head">
                                        <div class="ww-panel-title"><i class="fas fa-tasks"></i> PETA</div>
                                        <button type="button" class="small-btn" id="addNewPetaBtn"><i class="fas fa-plus"></i> Add</button>
                                    </div>
                                    <div class="ww-rows" id="newPetaRows"></div>
                                    <div class="ww-summary">Percentage Score: <b id="newPetaAvg">—</b></div>
                                </div>

                                <div class="ww-panel">
                                    <div class="ww-panel-head">
                                        <div class="ww-panel-title"><i class="fas fa-clipboard-list"></i> Other Performance Tasks</div>
                                        <button type="button" class="small-btn" id="addNewOtherPerformanceBtn"><i class="fas fa-plus"></i> Add</button>
                                    </div>
                                    <div class="ww-rows" id="newOtherPerformanceRows"></div>
                                    <div class="ww-summary">Percentage Score: <b id="newOtherPerformanceAvg">—</b></div>
                                </div>
                            </div>

                            <div class="ww-summary" style="margin-top:10px;">Combined (Performance Tasks): <b id="newPerformanceCombined">—</b></div>
                            <input type="number" id="newPerformance" min="0" max="100" placeholder="Auto-calculated" readonly style="margin-top:10px;">
                        </div>
                        <div class="form-group">
                            <label>Quarterly Assessment (20%) *</label>
                            <input type="number" id="newQuarterly" min="0" max="100" placeholder="0-100">
                        </div>
                    </div>
                    <button class="submit-btn" style="margin-top:1rem;" onclick="addSubjectForStudent('${student.id}')">
                        <i class="fas fa-plus"></i> Add Subject
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // initialize Activities/Quizzes builder for new subjects
        initWrittenWorksBuilder({
            activityRowsEl: modal.querySelector('#newActivityRows'),
            quizRowsEl: modal.querySelector('#newQuizRows'),
            addActivityBtn: modal.querySelector('#addNewActivityBtn'),
            addQuizBtn: modal.querySelector('#addNewQuizBtn'),
            activityAvgEl: modal.querySelector('#newActivityAvg'),
            quizAvgEl: modal.querySelector('#newQuizAvg'),
            combinedEl: modal.querySelector('#newWrittenCombined'),
            writtenInputEl: modal.querySelector('#newWritten'),
            initialActivities: [],
            initialQuizzes: []
        });

        // initialize Performance Tasks (PETA) builder for new subjects (split like Activities/Quizzes)
        initDualEntriesBuilder({
            leftRowsEl: modal.querySelector('#newPetaRows'),
            rightRowsEl: modal.querySelector('#newOtherPerformanceRows'),
            addLeftBtn: modal.querySelector('#addNewPetaBtn'),
            addRightBtn: modal.querySelector('#addNewOtherPerformanceBtn'),
            leftAvgEl: modal.querySelector('#newPetaAvg'),
            rightAvgEl: modal.querySelector('#newOtherPerformanceAvg'),
            combinedEl: modal.querySelector('#newPerformanceCombined'),
            outputEl: modal.querySelector('#newPerformance'),
            initialLeftEntries: [],
            initialRightEntries: [],
            leftTitlePrefix: 'PETA',
            rightTitlePrefix: 'Task'
        });

    } catch (error) {
        console.error('Error loading student subjects:', error);
        alert('Error loading subjects');
    }
}

function round1(value) {
    return Math.round(value * 10) / 10;
}

function round0(value) {
    return Math.round(value);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function clampScore(score) {
    if (!Number.isFinite(score)) return null;
    return Math.min(100, Math.max(0, score));
}

function toFiniteNumber(value) {
    const num = typeof value === 'number' ? value : parseFloat(String(value ?? ''));
    return Number.isFinite(num) ? num : null;
}

function clampNonNegative(value) {
    if (!Number.isFinite(value)) return null;
    return Math.max(0, value);
}

function computePercentageScore(entries) {
    const list = Array.isArray(entries) ? entries : [];
    let totalScore = 0;
    let totalOver = 0;

    for (const entry of list) {
        const score = clampNonNegative(toFiniteNumber(entry?.score));
        const over = clampNonNegative(toFiniteNumber(entry?.over));
        if (score === null || over === null) continue;
        if (over <= 0) continue;
        totalScore += Math.min(score, over);
        totalOver += over;
    }

    if (totalOver <= 0) return null;
    const percent = (totalScore / totalOver) * 100;
    return round1(clampScore(percent));
}

// DepEd K–12 grading uses transmutation. A common linear transmutation maps
// Initial Grade 60 -> Quarterly Grade 75 and Initial Grade 100 -> 100.
function transmuteDepEd(initialGrade) {
    const ig = clampScore(toFiniteNumber(initialGrade));
    if (ig === null) return null;
    const transmuted = (ig * 0.625) + 37.5;
    const qg = round0(transmuted);
    return Math.min(100, Math.max(60, qg));
}

function computeDepEdFinalGrade({ writtenPS, performancePS, quarterlyPS }) {
    const w = clampScore(toFiniteNumber(writtenPS));
    const p = clampScore(toFiniteNumber(performancePS));
    const q = clampScore(toFiniteNumber(quarterlyPS));
    if (w === null || p === null || q === null) return { initialGrade: null, quarterlyGrade: null };
    const initialGrade = round1((w * 0.30) + (p * 0.50) + (q * 0.20));
    const quarterlyGrade = transmuteDepEd(initialGrade);
    return { initialGrade, quarterlyGrade };
}

function computeWrittenWorksScore(subjectOrParts) {
    const activities = Array.isArray(subjectOrParts?.writtenActivities) ? subjectOrParts.writtenActivities : (subjectOrParts?.activities || []);
    const quizzes = Array.isArray(subjectOrParts?.writtenQuizzes) ? subjectOrParts.writtenQuizzes : (subjectOrParts?.quizzes || []);
    const combined = [...activities, ...quizzes];
    const ps = computePercentageScore(combined);
    if (ps === null) {
        const legacy = clampScore(parseFloat(subjectOrParts?.written));
        return legacy === null ? null : round1(legacy);
    }
    return ps;
}

function formatWrittenWorksScore(subject) {
    const score = computeWrittenWorksScore(subject);
    return score === null ? '' : String(score);
}

function computePerformanceTasksScore(subject) {
    const peta = Array.isArray(subject?.performancePetaTasks)
        ? subject.performancePetaTasks
        : (Array.isArray(subject?.performancePeta) ? subject.performancePeta : []);
    const other = Array.isArray(subject?.performanceOtherTasks)
        ? subject.performanceOtherTasks
        : (Array.isArray(subject?.performanceOther) ? subject.performanceOther : []);
    const legacyTasks = Array.isArray(subject?.performanceTasks) ? subject.performanceTasks : [];
    const entries = (peta.length || other.length) ? [...peta, ...other] : legacyTasks;

    const ps = computePercentageScore(entries);
    if (ps === null) {
        const legacy = clampScore(parseFloat(subject?.performance));
        return legacy === null ? null : round1(legacy);
    }
    return ps;
}

function formatPerformanceTasksScore(subject) {
    const score = computePerformanceTasksScore(subject);
    return score === null ? '' : String(score);
}

function createWwRow({ title = '', date = '', score = '', over = '', seeded = false } = {}) {
    const row = document.createElement('div');
    row.className = 'ww-row';
    if (seeded) row.dataset.seeded = '1';
    row.innerHTML = `
        <input type="text" class="ww-title" placeholder="Title" value="${escapeHtml(title)}" />
        <input type="date" class="ww-date" value="${escapeHtml(date)}" />
        <input type="number" class="ww-score" min="0" step="0.01" placeholder="Score" value="${escapeHtml(String(score))}" />
        <input type="number" class="ww-over" min="1" step="0.01" placeholder="Over" value="${escapeHtml(String(over))}" />
        <button type="button" class="icon-btn ww-remove" title="Remove">&times;</button>
    `;
    return row;
}

function readWwEntries(rowsEl) {
    if (!rowsEl) return [];
    return Array.from(rowsEl.querySelectorAll('.ww-row'))
        .map(row => {
            const seeded = row.dataset.seeded === '1';
            const title = row.querySelector('.ww-title')?.value || '';
            const date = row.querySelector('.ww-date')?.value || '';
            const score = row.querySelector('.ww-score')?.value || '';
            const over = row.querySelector('.ww-over')?.value || '';
            return { title, date, score, over, _seeded: seeded };
        })
        .filter(e => {
            const title = String(e.title || '').trim();
            const date = String(e.date || '').trim();
            const score = String(e.score ?? '').trim();
            const over = String(e.over ?? '').trim();
            const hasOther = Boolean(date || score || over);
            const hasAny = Boolean(title || hasOther);
            if (!hasAny) return false;
            if (e._seeded && !hasOther) return false;
            return true;
        })
        .map(({ _seeded, ...rest }) => rest);
}

function validateWwEntries(entries) {
    const list = Array.isArray(entries) ? entries : [];
    if (!list.length) return { ok: false, error: 'Please add at least one entry.' };

    for (const entry of list) {
        const title = (entry?.title || '').trim();
        const date = (entry?.date || '').trim();
        const scoreStr = String(entry?.score ?? '').trim();
        const overStr = String(entry?.over ?? '').trim();
        const score = parseFloat(scoreStr);
        const over = parseFloat(overStr);

        if (!title) return { ok: false, error: 'Please provide a title for each entry.' };
        if (!date) return { ok: false, error: 'Please provide a date for each entry.' };
        if (scoreStr === '' || !Number.isFinite(score)) return { ok: false, error: 'Please provide a numeric score for each entry.' };
        if (overStr === '' || !Number.isFinite(over) || over <= 0) return { ok: false, error: 'Please provide a valid "over" value for each entry.' };
        if (score < 0) return { ok: false, error: 'Scores cannot be negative.' };
        if (score > over) return { ok: false, error: 'Score cannot be greater than "over".' };
    }

    return { ok: true };
}

function updateWrittenWorksSummary({ activityRowsEl, quizRowsEl, activityAvgEl, quizAvgEl, combinedEl, writtenInputEl }) {
    const activities = readWwEntries(activityRowsEl);
    const quizzes = readWwEntries(quizRowsEl);

    const activityPS = computePercentageScore(activities);
    const quizPS = computePercentageScore(quizzes);
    const combinedPS = computeWrittenWorksScore({ activities, quizzes });

    if (activityAvgEl) activityAvgEl.textContent = activityPS === null ? '—' : String(activityPS);
    if (quizAvgEl) quizAvgEl.textContent = quizPS === null ? '—' : String(quizPS);
    if (combinedEl) combinedEl.textContent = combinedPS === null ? '—' : String(combinedPS);
    if (writtenInputEl) writtenInputEl.value = combinedPS === null ? '' : String(combinedPS);
}

function updateSingleEntriesSummary({ rowsEl, scoreEl, outputEl }) {
    const entries = readWwEntries(rowsEl);
    const ps = computePercentageScore(entries);
    if (scoreEl) scoreEl.textContent = ps === null ? '—' : String(ps);
    if (outputEl) outputEl.value = ps === null ? '' : String(ps);
}

function updateDualEntriesSummary({ leftRowsEl, rightRowsEl, leftAvgEl, rightAvgEl, combinedEl, outputEl }) {
    const left = readWwEntries(leftRowsEl);
    const right = readWwEntries(rightRowsEl);

    const leftPS = computePercentageScore(left);
    const rightPS = computePercentageScore(right);
    const combinedPS = computePercentageScore([...left, ...right]);

    if (leftAvgEl) leftAvgEl.textContent = leftPS === null ? '—' : String(leftPS);
    if (rightAvgEl) rightAvgEl.textContent = rightPS === null ? '—' : String(rightPS);
    if (combinedEl) combinedEl.textContent = combinedPS === null ? '—' : String(combinedPS);
    if (outputEl) outputEl.value = combinedPS === null ? '' : String(combinedPS);
}

function initDualEntriesBuilder({
    leftRowsEl,
    rightRowsEl,
    addLeftBtn,
    addRightBtn,
    leftAvgEl,
    rightAvgEl,
    combinedEl,
    outputEl,
    initialLeftEntries = [],
    initialRightEntries = [],
    leftTitlePrefix = 'Entry',
    rightTitlePrefix = 'Entry'
}) {
    if (!leftRowsEl || !rightRowsEl) return;

    const addRow = (rowsEl, data) => {
        rowsEl.appendChild(createWwRow(data));
    };

    if (initialLeftEntries.length) {
        initialLeftEntries.forEach((e, idx) => {
            const title = (e?.title || '').trim() || `${leftTitlePrefix} ${idx + 1}`;
            const over = e?.over ?? (e?.total ?? 100);
            addRow(leftRowsEl, {
                title,
                date: e?.date || '',
                score: e?.score ?? '',
                over: over === '' || over === undefined ? 100 : over
            });
        });
    } else {
        addRow(leftRowsEl, { title: `${leftTitlePrefix} 1`, seeded: true });
    }

    if (initialRightEntries.length) {
        initialRightEntries.forEach((e, idx) => {
            const title = (e?.title || '').trim() || `${rightTitlePrefix} ${idx + 1}`;
            const over = e?.over ?? (e?.total ?? 100);
            addRow(rightRowsEl, {
                title,
                date: e?.date || '',
                score: e?.score ?? '',
                over: over === '' || over === undefined ? 100 : over
            });
        });
    } else {
        addRow(rightRowsEl, { title: `${rightTitlePrefix} 1`, seeded: true });
    }

    const handleChange = () => updateDualEntriesSummary({ leftRowsEl, rightRowsEl, leftAvgEl, rightAvgEl, combinedEl, outputEl });

    addLeftBtn?.addEventListener('click', () => {
        const count = leftRowsEl.querySelectorAll('.ww-row').length;
        addRow(leftRowsEl, { title: `${leftTitlePrefix} ${count + 1}` });
        handleChange();
    });

    addRightBtn?.addEventListener('click', () => {
        const count = rightRowsEl.querySelectorAll('.ww-row').length;
        addRow(rightRowsEl, { title: `${rightTitlePrefix} ${count + 1}` });
        handleChange();
    });

    const delegate = (e) => {
        const target = e.target;
        if (!(target instanceof Element)) return;
        if (target.classList.contains('ww-remove')) {
            target.closest('.ww-row')?.remove();
            handleChange();
        }
        if (
            target.classList.contains('ww-title') ||
            target.classList.contains('ww-date') ||
            target.classList.contains('ww-score') ||
            target.classList.contains('ww-over')
        ) {
            const row = target.closest('.ww-row');
            if (row?.dataset?.seeded) delete row.dataset.seeded;
            handleChange();
        }
    };

    leftRowsEl.addEventListener('input', delegate);
    rightRowsEl.addEventListener('input', delegate);
    leftRowsEl.addEventListener('click', delegate);
    rightRowsEl.addEventListener('click', delegate);

    handleChange();
}

function initSingleEntriesBuilder({
    rowsEl,
    addBtn,
    scoreEl,
    outputEl,
    initialEntries = [],
    defaultTitlePrefix = 'Entry'
}) {
    if (!rowsEl) return;

    const addRow = (data) => {
        rowsEl.appendChild(createWwRow(data));
    };

    if (initialEntries.length) {
        initialEntries.forEach((e, idx) => {
            const title = (e?.title || '').trim() || `${defaultTitlePrefix} ${idx + 1}`;
            const over = e?.over ?? (e?.total ?? 100);
            addRow({
                title,
                date: e?.date || '',
                score: e?.score ?? '',
                over: over === '' || over === undefined ? 100 : over
            });
        });
    } else {
        addRow({ title: `${defaultTitlePrefix} 1`, seeded: true });
    }

    const handleChange = () => updateSingleEntriesSummary({ rowsEl, scoreEl, outputEl });

    addBtn?.addEventListener('click', () => {
        const count = rowsEl.querySelectorAll('.ww-row').length;
        addRow({ title: `${defaultTitlePrefix} ${count + 1}` });
        handleChange();
    });

    const delegate = (e) => {
        const target = e.target;
        if (!(target instanceof Element)) return;
        if (target.classList.contains('ww-remove')) {
            target.closest('.ww-row')?.remove();
            handleChange();
        }
        if (
            target.classList.contains('ww-title') ||
            target.classList.contains('ww-date') ||
            target.classList.contains('ww-score') ||
            target.classList.contains('ww-over')
        ) {
            const row = target.closest('.ww-row');
            if (row?.dataset?.seeded) delete row.dataset.seeded;
            handleChange();
        }
    };

    rowsEl.addEventListener('input', delegate);
    rowsEl.addEventListener('click', delegate);

    handleChange();
}

function initWrittenWorksBuilder({
    activityRowsEl,
    quizRowsEl,
    addActivityBtn,
    addQuizBtn,
    activityAvgEl,
    quizAvgEl,
    combinedEl,
    writtenInputEl,
    initialActivities = [],
    initialQuizzes = []
}) {
    if (!activityRowsEl || !quizRowsEl) return;

    const addRow = (rowsEl, data) => {
        const row = createWwRow(data);
        rowsEl.appendChild(row);
    };

    // Seed at least one row for better UX
    if (initialActivities.length) {
        initialActivities.forEach((a, idx) => addRow(activityRowsEl, {
            title: (a?.title || '').trim() || `Activity ${idx + 1}`,
            date: a?.date || '',
            score: a?.score ?? '',
            over: a?.over ?? 100
        }));
    } else {
        addRow(activityRowsEl, { title: 'Activity 1', seeded: true });
    }

    if (initialQuizzes.length) {
        initialQuizzes.forEach((q, idx) => addRow(quizRowsEl, {
            title: (q?.title || '').trim() || `Quiz ${idx + 1}`,
            date: q?.date || '',
            score: q?.score ?? '',
            over: q?.over ?? 100
        }));
    } else {
        addRow(quizRowsEl, { title: 'Quiz 1', seeded: true });
    }

    const handleChange = () => updateWrittenWorksSummary({ activityRowsEl, quizRowsEl, activityAvgEl, quizAvgEl, combinedEl, writtenInputEl });

    addActivityBtn?.addEventListener('click', () => {
        const count = activityRowsEl.querySelectorAll('.ww-row').length;
        addRow(activityRowsEl, { title: `Activity ${count + 1}` });
        handleChange();
    });
    addQuizBtn?.addEventListener('click', () => {
        const count = quizRowsEl.querySelectorAll('.ww-row').length;
        addRow(quizRowsEl, { title: `Quiz ${count + 1}` });
        handleChange();
    });

    const delegate = (e) => {
        const target = e.target;
        if (!(target instanceof Element)) return;
        if (target.classList.contains('ww-remove')) {
            target.closest('.ww-row')?.remove();
            handleChange();
        }
        if (
            target.classList.contains('ww-title') ||
            target.classList.contains('ww-date') ||
            target.classList.contains('ww-score') ||
            target.classList.contains('ww-over')
        ) {
            const row = target.closest('.ww-row');
            if (row?.dataset?.seeded) delete row.dataset.seeded;
            handleChange();
        }
    };

    activityRowsEl.addEventListener('input', delegate);
    quizRowsEl.addEventListener('input', delegate);
    activityRowsEl.addEventListener('click', delegate);
    quizRowsEl.addEventListener('click', delegate);

    handleChange();
}

// Add a subject with components for a student
async function addSubjectForStudent(studentId) {
    const title = document.getElementById('newSubjectName').value.trim();
    const quarterLabel = (document.getElementById('newQuarterLabel')?.value || '').trim();
    const written = parseFloat(document.getElementById('newWritten').value);
    const performance = parseFloat(document.getElementById('newPerformance').value);
    const quarterly = parseFloat(document.getElementById('newQuarterly').value);

    const writtenActivities = readWwEntries(document.getElementById('newActivityRows'));
    const writtenQuizzes = readWwEntries(document.getElementById('newQuizRows'));
    const performancePetaTasks = readWwEntries(document.getElementById('newPetaRows'));
    const performanceOtherTasks = readWwEntries(document.getElementById('newOtherPerformanceRows'));
    const performanceTasks = [...performancePetaTasks, ...performanceOtherTasks];

    if (!title || !quarterLabel || isNaN(written) || isNaN(performance) || isNaN(quarterly)) {
        alert('Please fill in subject name, quarter, and all grade components');
        return;
    }

    const combinedEntries = [...writtenActivities, ...writtenQuizzes];
    if (combinedEntries.length === 0) {
        alert('Please add at least one Activity or Quiz entry for Written Works.');
        return;
    }
    const activitiesCheck = writtenActivities.length ? validateWwEntries(writtenActivities) : { ok: true };
    const quizzesCheck = writtenQuizzes.length ? validateWwEntries(writtenQuizzes) : { ok: true };
    if (!activitiesCheck.ok) {
        alert('Activities: ' + activitiesCheck.error);
        return;
    }
    if (!quizzesCheck.ok) {
        alert('Quizzes: ' + quizzesCheck.error);
        return;
    }

    if (!performanceTasks.length) {
        alert('Please add at least one Performance Task (PETA) entry.');
        return;
    }
    const petaCheck = performancePetaTasks.length ? validateWwEntries(performancePetaTasks) : { ok: true };
    const otherCheck = performanceOtherTasks.length ? validateWwEntries(performanceOtherTasks) : { ok: true };
    if (!petaCheck.ok) {
        alert('PETA: ' + petaCheck.error);
        return;
    }
    if (!otherCheck.ok) {
        alert('Other Performance Tasks: ' + otherCheck.error);
        return;
    }

    if (written < 0 || written > 100 || performance < 0 || performance > 100 || quarterly < 0 || quarterly > 100) {
        alert('All grades must be between 0 and 100');
        return;
    }

    const { quarterlyGrade } = computeDepEdFinalGrade({ writtenPS: written, performancePS: performance, quarterlyPS: quarterly });
    if (quarterlyGrade === null) {
        alert('Unable to compute final grade. Please check inputs.');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getStudents' })
        });
        const result = await response.json();
        const student = result.students.find(s => String(s.id) === String(studentId));

        if (!student) {
            alert('Student not found');
            return;
        }

        const subjects = student.subjects || [];
        subjects.push({
            title,
            quarterLabel,
            written: written.toString(),
            writtenActivities,
            writtenQuizzes,
            performance: performance.toString(),
            performanceTasks,
            performancePetaTasks,
            performanceOtherTasks,
            quarterly: quarterly.toString(),
            final: quarterlyGrade.toString()
        });

        const saveResponse = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'updateGrades',
                studentId: studentId,
                subjects
            })
        });
        const saveResult = await saveResponse.json();

        if (saveResult.success) {
            showNotif('Subject added successfully');
            document.querySelectorAll('.modal').forEach(m => m.remove());
            loadStudentsList();
            openSubjectsModal(studentId);
        } else {
            alert('Error: ' + saveResult.error);
        }
    } catch (error) {
        console.error('Error adding subject:', error);
        alert('An error occurred while adding the subject');
    }
}

// Delete a subject from a student
async function deleteSubject(studentId, subjectIndex) {
    if (!confirm('Remove this subject from the student?')) return;

    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getStudents' })
        });
        const result = await response.json();
        const student = result.students.find(s => String(s.id) === String(studentId));

        if (!student) {
            alert('Student not found');
            return;
        }

        const subjects = student.subjects || [];
        subjects.splice(subjectIndex, 1);

        const saveResponse = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'updateGrades',
                studentId: studentId,
                subjects
            })
        });
        const saveResult = await saveResponse.json();

        if (saveResult.success) {
            showNotif('Subject removed');
            document.querySelectorAll('.modal').forEach(m => m.remove());
            loadStudentsList();
            openSubjectsModal(studentId);
        } else {
            alert('Error: ' + saveResult.error);
        }
    } catch (error) {
        console.error('Error deleting subject:', error);
        alert('An error occurred while deleting the subject');
    }
}

// Edit an existing subject's grades
async function editSubject(studentId, subjectIndex) {
    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getStudents' })
        });
        const result = await response.json();
        const student = result.students.find(s => String(s.id) === String(studentId));

        if (!student) {
            alert('Student not found');
            return;
        }

        const subjects = student.subjects || [];
        const subject = subjects[subjectIndex];
        if (!subject) {
            alert('Subject not found');
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'modal active';

        const titleValue = subject.title || '';
        const quarterValue = subject.quarterLabel || subject.quarter || '';
        const activities = Array.isArray(subject.writtenActivities) ? subject.writtenActivities : [];
        const quizzes = Array.isArray(subject.writtenQuizzes) ? subject.writtenQuizzes : [];
        const performanceValue = subject.performance ?? '';
        const legacyPerformanceTasks = Array.isArray(subject.performanceTasks) ? subject.performanceTasks : [];
        const performancePetaTasks = Array.isArray(subject.performancePetaTasks)
            ? subject.performancePetaTasks
            : (Array.isArray(subject.performancePeta) ? subject.performancePeta : []);
        const performanceOtherTasks = Array.isArray(subject.performanceOtherTasks)
            ? subject.performanceOtherTasks
            : (Array.isArray(subject.performanceOther) ? subject.performanceOther : []);

        const hasSplitPerf = performancePetaTasks.length || performanceOtherTasks.length;
        const initPetaTasks = hasSplitPerf ? performancePetaTasks : legacyPerformanceTasks;
        const initOtherTasks = hasSplitPerf ? performanceOtherTasks : [];
        const quarterlyValue = subject.quarterly ?? '';

        modal.innerHTML = `
            <div class="modal-content" style="width: 94vw; max-width: 980px; max-height: 80vh; overflow-y: auto; background:#ffffff; color:#111827; text-align:left;">
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                <h2 style="color:#2563eb; margin-bottom: 0.25rem;"><i class="fas fa-pen"></i> Edit Subject</h2>
                <p style="color:#6b7280; margin-top:0; margin-bottom: 1rem; font-size:0.9rem;">Update Activities + Quizzes (Written Works), Performance Tasks, and Quarterly Assessment.</p>

                <div class="grade-inputs">
                    <div class="form-group">
                        <label>Subject Name *</label>
                        <input type="text" id="editSubjectName" value="${escapeHtml(titleValue)}" placeholder="e.g., Mathematics" />
                    </div>
                    <div class="form-group">
                        <label>Quarter *</label>
                        <select id="editQuarterLabel">
                            <option value="">Select Quarter</option>
                            <option value="1st Quarter">1st Quarter</option>
                            <option value="2nd Quarter">2nd Quarter</option>
                            <option value="3rd Quarter">3rd Quarter</option>
                            <option value="4th Quarter">4th Quarter</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Written Works (30%) = Activities + Quizzes *</label>

                        <div class="ww-grid">
                            <div class="ww-panel">
                                <div class="ww-panel-head">
                                    <div class="ww-panel-title"><i class="fas fa-clipboard-check"></i> Activities</div>
                                    <button type="button" class="small-btn" id="addEditActivityBtn"><i class="fas fa-plus"></i> Add</button>
                                </div>
                                <div class="ww-rows" id="editActivityRows"></div>
                                <div class="ww-summary">Percentage Score: <b id="editActivityAvg">—</b></div>
                            </div>

                            <div class="ww-panel">
                                <div class="ww-panel-head">
                                    <div class="ww-panel-title"><i class="fas fa-pen"></i> Quizzes</div>
                                    <button type="button" class="small-btn" id="addEditQuizBtn"><i class="fas fa-plus"></i> Add</button>
                                </div>
                                <div class="ww-rows" id="editQuizRows"></div>
                                <div class="ww-summary">Percentage Score: <b id="editQuizAvg">—</b></div>
                            </div>
                        </div>

                        <div class="ww-summary" style="margin-top:10px;">Combined (Activities + Quizzes): <b id="editWrittenCombined">—</b></div>
                        <input type="number" id="editWritten" min="0" max="100" placeholder="Auto-calculated" readonly style="margin-top:10px;">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Performance Tasks (50%) (PETA) *</label>
                        <div class="ww-grid" style="margin-top:8px;">
                            <div class="ww-panel">
                                <div class="ww-panel-head">
                                    <div class="ww-panel-title"><i class="fas fa-tasks"></i> PETA</div>
                                    <button type="button" class="small-btn" id="addEditPetaBtn"><i class="fas fa-plus"></i> Add</button>
                                </div>
                                <div class="ww-rows" id="editPetaRows"></div>
                                <div class="ww-summary">Percentage Score: <b id="editPetaAvg">—</b></div>
                            </div>

                            <div class="ww-panel">
                                <div class="ww-panel-head">
                                    <div class="ww-panel-title"><i class="fas fa-clipboard-list"></i> Other Performance Tasks</div>
                                    <button type="button" class="small-btn" id="addEditOtherPerformanceBtn"><i class="fas fa-plus"></i> Add</button>
                                </div>
                                <div class="ww-rows" id="editOtherPerformanceRows"></div>
                                <div class="ww-summary">Percentage Score: <b id="editOtherPerformanceAvg">—</b></div>
                            </div>
                        </div>
                        <div class="ww-summary" style="margin-top:10px;">Combined (Performance Tasks): <b id="editPerformanceCombined">—</b></div>
                        <input type="number" id="editPerformance" min="0" max="100" placeholder="Auto-calculated" readonly style="margin-top:10px;" value="${escapeHtml(String(performanceValue))}">
                    </div>
                    <div class="form-group">
                        <label>Quarterly Assessment (20%) *</label>
                        <input type="number" id="editQuarterly" min="0" max="100" placeholder="0-100" value="${escapeHtml(String(quarterlyValue))}">
                    </div>
                </div>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top: 14px;">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()" style="width:auto; padding:0.6rem 1rem; border-radius: 12px;">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditSubjectBtn" style="width:auto; padding:0.6rem 1rem; border-radius: 12px;">Save</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const quarterSelect = modal.querySelector('#editQuarterLabel');
        if (quarterSelect) quarterSelect.value = quarterValue;

        initWrittenWorksBuilder({
            activityRowsEl: modal.querySelector('#editActivityRows'),
            quizRowsEl: modal.querySelector('#editQuizRows'),
            addActivityBtn: modal.querySelector('#addEditActivityBtn'),
            addQuizBtn: modal.querySelector('#addEditQuizBtn'),
            activityAvgEl: modal.querySelector('#editActivityAvg'),
            quizAvgEl: modal.querySelector('#editQuizAvg'),
            combinedEl: modal.querySelector('#editWrittenCombined'),
            writtenInputEl: modal.querySelector('#editWritten'),
            initialActivities: activities,
            initialQuizzes: quizzes
        });

        // initialize Performance Tasks (PETA) builder (split like Activities/Quizzes)
        initDualEntriesBuilder({
            leftRowsEl: modal.querySelector('#editPetaRows'),
            rightRowsEl: modal.querySelector('#editOtherPerformanceRows'),
            addLeftBtn: modal.querySelector('#addEditPetaBtn'),
            addRightBtn: modal.querySelector('#addEditOtherPerformanceBtn'),
            leftAvgEl: modal.querySelector('#editPetaAvg'),
            rightAvgEl: modal.querySelector('#editOtherPerformanceAvg'),
            combinedEl: modal.querySelector('#editPerformanceCombined'),
            outputEl: modal.querySelector('#editPerformance'),
            initialLeftEntries: initPetaTasks,
            initialRightEntries: initOtherTasks,
            leftTitlePrefix: 'PETA',
            rightTitlePrefix: 'Task'
        });

        // If no performance entries exist but legacy performance exists, keep it visible
        if (!legacyPerformanceTasks.length && !hasSplitPerf && performanceValue !== undefined && performanceValue !== null && String(performanceValue).trim() !== '') {
            const legacyPerf = clampScore(parseFloat(performanceValue));
            if (legacyPerf !== null) {
                const v = String(round1(legacyPerf));
                const perfInput = modal.querySelector('#editPerformance');
                const perfAvg = modal.querySelector('#editPerformanceCombined');
                if (perfInput) perfInput.value = v;
                if (perfAvg) perfAvg.textContent = v;
            }
        }

        // If no Activities/Quizzes exist but legacy written exists, keep it visible
        if ((!activities.length && !quizzes.length) && subject.written) {
            const legacy = clampScore(parseFloat(subject.written));
            if (legacy !== null) {
                modal.querySelector('#editWritten').value = String(round1(legacy));
                modal.querySelector('#editWrittenCombined').textContent = String(round1(legacy));
            }
        }

        modal.querySelector('#saveEditSubjectBtn')?.addEventListener('click', async () => {
            const newTitle = (modal.querySelector('#editSubjectName')?.value || '').trim();
            const newQuarter = (modal.querySelector('#editQuarterLabel')?.value || '').trim();
            const newWritten = parseFloat(modal.querySelector('#editWritten')?.value);
            const newPerformance = parseFloat(modal.querySelector('#editPerformance')?.value);
            const newQuarterlyScore = parseFloat(modal.querySelector('#editQuarterly')?.value);

            const newActivities = readWwEntries(modal.querySelector('#editActivityRows'));
            const newQuizzes = readWwEntries(modal.querySelector('#editQuizRows'));
            const newPerformancePetaTasks = readWwEntries(modal.querySelector('#editPetaRows'));
            const newPerformanceOtherTasks = readWwEntries(modal.querySelector('#editOtherPerformanceRows'));
            const newPerformanceTasks = [...newPerformancePetaTasks, ...newPerformanceOtherTasks];

            if (!newTitle || !newQuarter || isNaN(newWritten) || isNaN(newPerformance) || isNaN(newQuarterlyScore)) {
                alert('Please fill in subject name, quarter, and all grade components');
                return;
            }
            const combinedEntries = [...newActivities, ...newQuizzes];
            if (combinedEntries.length === 0) {
                // Backward compatibility: allow saving if this subject previously had legacy written score
                const hadLegacy = subject && subject.written !== undefined && subject.written !== null && String(subject.written).trim() !== '';
                if (!hadLegacy) {
                    alert('Please add at least one Activity or Quiz entry for Written Works.');
                    return;
                }
            } else {
                const activitiesCheck = newActivities.length ? validateWwEntries(newActivities) : { ok: true };
                const quizzesCheck = newQuizzes.length ? validateWwEntries(newQuizzes) : { ok: true };
                if (!activitiesCheck.ok) {
                    alert('Activities: ' + activitiesCheck.error);
                    return;
                }
                if (!quizzesCheck.ok) {
                    alert('Quizzes: ' + quizzesCheck.error);
                    return;
                }
            }

            if (!newPerformanceTasks.length) {
                // Backward compatibility: allow saving if this subject previously had legacy performance score
                const hadLegacyPerf = subject && subject.performance !== undefined && subject.performance !== null && String(subject.performance).trim() !== '';
                if (!hadLegacyPerf) {
                    alert('Please add at least one Performance Task (PETA) entry.');
                    return;
                }
            } else {
                const petaCheck = newPerformancePetaTasks.length ? validateWwEntries(newPerformancePetaTasks) : { ok: true };
                const otherCheck = newPerformanceOtherTasks.length ? validateWwEntries(newPerformanceOtherTasks) : { ok: true };
                if (!petaCheck.ok) {
                    alert('PETA: ' + petaCheck.error);
                    return;
                }
                if (!otherCheck.ok) {
                    alert('Other Performance Tasks: ' + otherCheck.error);
                    return;
                }
            }
            if (
                newWritten < 0 || newWritten > 100 ||
                newPerformance < 0 || newPerformance > 100 ||
                newQuarterlyScore < 0 || newQuarterlyScore > 100
            ) {
                alert('All grades must be between 0 and 100');
                return;
            }

            const { quarterlyGrade } = computeDepEdFinalGrade({ writtenPS: newWritten, performancePS: newPerformance, quarterlyPS: newQuarterlyScore });
            if (quarterlyGrade === null) {
                alert('Unable to compute final grade. Please check inputs.');
                return;
            }

            subjects[subjectIndex] = {
                ...subject,
                title: newTitle,
                quarterLabel: newQuarter,
                written: round1(newWritten).toString(),
                writtenActivities: newActivities,
                writtenQuizzes: newQuizzes,
                performanceTasks: newPerformanceTasks,
                performancePetaTasks: newPerformancePetaTasks,
                performanceOtherTasks: newPerformanceOtherTasks,
                performance: round1(newPerformance).toString(),
                quarterly: round1(newQuarterlyScore).toString(),
                final: quarterlyGrade.toString()
            };

            const saveResponse = await fetch(`${API_BASE}/admin.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'updateGrades',
                    studentId: studentId,
                    subjects
                })
            });
            const saveResult = await saveResponse.json();

            if (saveResult.success) {
                showNotif('Subject updated successfully');
                document.querySelectorAll('.modal').forEach(m => m.remove());
                loadStudentsList();
                openSubjectsModal(studentId);
            } else {
                alert('Error: ' + saveResult.error);
            }
        });
    } catch (error) {
        console.error('Error updating subject:', error);
        alert('An error occurred while updating the subject');
    }
}

// Show Add Student Modal
function showAddStudentModal() {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'addStudentModal';
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            <h2 style="margin-bottom: 1.5rem;">Add New Student</h2>
            <form id="addStudentForm" class="stack">
                <div class="form-group">
                    <label>Student Name *</label>
                    <input type="text" id="studentName" required placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label>Grade *</label>
                    <select id="studentGrade" required>
                        <option value="">Select Grade</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="Grade 12">Grade 12</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section *</label>
                    <input type="text" id="studentSection" required placeholder="e.g., CCS-A">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Student
                </button>
                <div id="addStudentError" style="color: #f44336; margin-top: 1rem; display: none;"></div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    
    document.getElementById('addStudentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const name = document.getElementById('studentName').value.trim();
        const grade = document.getElementById('studentGrade').value;
        const section = document.getElementById('studentSection').value.trim();
        
        try {
            const response = await fetch(`${API_BASE}/admin.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'addStudent',
                    name: name,
                    grade: grade,
                    section: section,
                    adminUsername: currentUser.username
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotif(`Student added! Child Code: ${result.student.childCode}`);
                modal.remove();
                renderAdminDashboard();
                
                // Show child code in a separate modal
                showChildCodeModal(result.student);
            } else {
                document.getElementById('addStudentError').textContent = result.error;
                document.getElementById('addStudentError').style.display = 'block';
            }
        } catch (error) {
            console.error('Error adding student:', error);
            document.getElementById('addStudentError').textContent = 'An error occurred.';
            document.getElementById('addStudentError').style.display = 'block';
        }
    });
}

// Show Child Code Modal
function showChildCodeModal(student) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 560px; background:#ffffff; color:#111827; text-align:left; backdrop-filter:none;">
            <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            <h2 style="margin-bottom: 1rem; color: #4CAF50;">
                <i class="fas fa-check-circle"></i> Student Added Successfully!
            </h2>
            <div style="background:#f0fdf4; padding: 1rem; border-radius: 12px; border: 1px solid #bbf7d0; margin: 1rem 0;">
                <div style="display:grid; gap:6px;">
                    <div><strong>Student:</strong> ${escapeHtml(student.name)}</div>
                    <div><strong>Grade:</strong> ${escapeHtml(student.grade)} - ${escapeHtml(student.section)}</div>
                </div>

                <div style="margin-top: 12px;">
                    <div style="margin-bottom:6px; color:#065f46; font-weight:800;"><i class="fas fa-key"></i> Child Code</div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input
                            type="text"
                            value="${escapeHtml(student.childCode)}"
                            readonly
                            style="flex:1; width:100%; padding: 12px 12px; border-radius: 10px; border: 2px solid #16a34a; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 1.05rem; letter-spacing: 1px; color:#166534; background:#ffffff;"
                            onclick="this.select()"
                        />
                        <button
                            type="button"
                            onclick="copyToClipboard('${student.childCode}'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 1200)"
                            style="white-space:nowrap; background: #16a34a; color: white; border: none; padding: 12px 14px; border-radius: 10px; cursor: pointer; font-weight:700;"
                        >Copy</button>
                    </div>
                    <div style="margin-top: 8px; font-size: 0.9rem; color: #374151; line-height:1.4;">
                        Share this code with the parent so they can link their account.
                    </div>
                </div>
            </div>

            <button type="button" onclick="this.closest('.modal').remove()"
                style="background: #2563eb; color: white; border: none; padding: 12px 16px; border-radius: 10px; cursor: pointer; width: 100%; font-weight:700;">
                Close
            </button>
        </div>
    `;
    document.body.appendChild(modal);
}

// Edit Student Grades
async function editStudentGrades(studentId) {
    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getStudents' })
        });
        
        const result = await response.json();
        const student = result.students.find(s => s.id === studentId);
        
        if (!student) {
            showNotif('Student not found');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal active';
        modal.id = 'editGradesModal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 750px; max-height: 85vh; overflow-y: auto;">
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                
                <!-- Header -->
                <div style="text-align: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid rgba(130, 178, 255, 0.3);">
                    <h2 style="margin: 0 0 0.5rem 0; color: #82b2ff;">
                        <i class="fas fa-edit"></i> Grade Management
                    </h2>
                    <h3 style="margin: 0; color: #fff; font-size: 1.3rem;">${student.name}</h3>
                    <p style="color: #aaa; margin: 0.3rem 0 0 0; font-size: 0.9rem;">
                        ${student.grade} - Section ${student.section}
                    </p>
                </div>
                
                <!-- Quick Actions -->
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                    <button onclick="addSubjectRow()" class="btn btn-primary" style="flex: 1; padding: 0.6rem;">
                        <i class="fas fa-plus-circle"></i> Add Subject
                    </button>
                    <button onclick="addMultipleSubjects()" class="btn" style="flex: 1; padding: 0.6rem; background: linear-gradient(135deg, #607D8B, #37474F);">
                        <i class="fas fa-layer-group"></i> Quick Add (Multiple)
                    </button>
                </div>
                
                <!-- Subjects List -->
                <div id="subjectsList" style="margin-bottom: 1rem;">
                    ${student.subjects && student.subjects.length > 0 ? 
                        student.subjects.map((sub, index) => {
                            const gradeNum = parseFloat(sub.grade);
                            const gradeColor = gradeNum >= 90 ? '#4CAF50' : gradeNum >= 80 ? '#8BC34A' : gradeNum >= 75 ? '#FFC107' : '#FF5722';
                            return `
                                <div class="subject-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.8rem; align-items: center; background: rgba(255,255,255,0.05); padding: 0.8rem; border-radius: 8px; border-left: 3px solid ${gradeColor};">
                                    <div style="flex: 1; display: flex; flex-direction: column; gap: 0.3rem;">
                                        <label style="font-size: 0.75rem; color: #82b2ff; font-weight: 600;">SUBJECT NAME</label>
                                        <input type="text" value="${sub.title}" class="subject-title" placeholder="e.g., Mathematics" 
                                            style="flex: 1; padding: 0.6rem; border-radius: 6px; border: 1px solid #82b2ff33; background: #fff; color: #222; font-size: 1rem;">
                                    </div>
                                    <div style="width: 120px; display: flex; flex-direction: column; gap: 0.3rem;">
                                        <label style="font-size: 0.75rem; color: #a5d6a7; font-weight: 600;">GRADE</label>
                                        <input type="number" value="${sub.grade}" class="subject-grade" placeholder="0-100" min="0" max="100" 
                                            style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #82b2ff33; background: #fff; color: #222; font-size: 1.1rem; font-weight: bold; text-align: center;">
                                    </div>
                                    <button onclick="this.closest('.subject-row').remove()" 
                                        style="background: linear-gradient(135deg, #f44336, #c62828); padding: 0.6rem 0.8rem; border-radius: 6px; border: none; color: white; cursor: pointer; margin-top: 1.2rem;"
                                        title="Remove subject">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            `;
                        }).join('') 
                        : `
                            <div style="text-align: center; padding: 2rem; background: rgba(255, 193, 7, 0.1); border-radius: 8px; border: 1px dashed #FFC107;">
                                <i class="fas fa-book-open" style="font-size: 2.5rem; color: #FFC107; margin-bottom: 0.5rem;"></i>
                                <p style="color: #FFC107; margin: 0;">No subjects added yet. Click "Add Subject" to begin.</p>
                            </div>
                        `
                    }
                </div>
                
                <!-- Statistics -->
                <div id="gradeStats" style="background: rgba(130, 178, 255, 0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: none;">
                    <div style="display: flex; justify-content: space-around; text-align: center;">
                        <div>
                            <div style="color: #82b2ff; font-size: 0.85rem; margin-bottom: 0.3rem;">SUBJECTS</div>
                            <div id="subjectCount" style="color: #fff; font-size: 1.5rem; font-weight: bold;">0</div>
                        </div>
                        <div>
                            <div style="color: #a5d6a7; font-size: 0.85rem; margin-bottom: 0.3rem;">AVERAGE</div>
                            <div id="avgGrade" style="color: #4CAF50; font-size: 1.5rem; font-weight: bold;">0</div>
                        </div>
                        <div>
                            <div style="color: #FFC107; font-size: 0.85rem; margin-bottom: 0.3rem;">HIGHEST</div>
                            <div id="highGrade" style="color: #FFC107; font-size: 1.5rem; font-weight: bold;">0</div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 0.8rem;">
                    <button onclick="this.closest('.modal').remove()" class="btn btn-secondary" style="flex: 1; padding: 0.8rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button onclick="saveGrades('${studentId}')" class="btn btn-primary" style="flex: 2; padding: 0.8rem; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);">
                        <i class="fas fa-save"></i> Save All Grades
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Update statistics
        updateGradeStats();
        
        // Add input listeners to update stats in real-time
        const gradeInputs = modal.querySelectorAll('.subject-grade');
        gradeInputs.forEach(input => {
            input.addEventListener('input', updateGradeStats);
        });
        
    } catch (error) {
        console.error('Error loading student:', error);
        showNotif('Error loading student data');
    }
}

// Update grade statistics
function updateGradeStats() {
    const rows = document.querySelectorAll('.subject-row');
    const grades = Array.from(rows)
        .map(row => parseFloat(row.querySelector('.subject-grade')?.value || 0))
        .filter(g => g > 0);
    
    const statsDiv = document.getElementById('gradeStats');
    if (grades.length > 0) {
        statsDiv.style.display = 'block';
        const avg = (grades.reduce((a, b) => a + b, 0) / grades.length).toFixed(1);
        const high = Math.max(...grades);
        
        document.getElementById('subjectCount').textContent = grades.length;
        document.getElementById('avgGrade').textContent = avg;
        document.getElementById('highGrade').textContent = high;
    } else {
        statsDiv.style.display = 'none';
    }
}

// Add multiple subjects at once
function addMultipleSubjects() {
    const commonSubjects = [
        'Mathematics', 'Science', 'English', 'Filipino',
        'Social Studies', 'Physical Education', 'Arts',
        'Computer Science', 'Values Education'
    ];
    
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.style.zIndex = '3001';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            <h3 style="margin-bottom: 1rem; color: #82b2ff;">
                <i class="fas fa-layer-group"></i> Quick Add Subjects
            </h3>
            <p style="color: #aaa; margin-bottom: 1rem; font-size: 0.9rem;">
                Select common subjects to add quickly. You can edit grades after adding.
            </p>
            <div id="quickSubjects" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 1rem;">
                ${commonSubjects.map(subject => `
                    <label style="display: flex; align-items: center; background: rgba(255,255,255,0.05); padding: 0.6rem; border-radius: 6px; cursor: pointer; border: 1px solid transparent; transition: all 0.2s;">
                        <input type="checkbox" value="${subject}" style="margin-right: 0.5rem; cursor: pointer;">
                        <span style="font-size: 0.9rem;">${subject}</span>
                    </label>
                `).join('')}
            </div>
            <button onclick="confirmQuickAdd()" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-check"></i> Add Selected Subjects
            </button>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Add hover effect
    modal.querySelectorAll('label').forEach(label => {
        label.addEventListener('mouseenter', () => {
            label.style.borderColor = '#82b2ff';
            label.style.background = 'rgba(130, 178, 255, 0.1)';
        });
        label.addEventListener('mouseleave', () => {
            if (!label.querySelector('input').checked) {
                label.style.borderColor = 'transparent';
                label.style.background = 'rgba(255,255,255,0.05)';
            }
        });
        label.querySelector('input').addEventListener('change', (e) => {
            if (e.target.checked) {
                label.style.borderColor = '#4CAF50';
                label.style.background = 'rgba(76, 175, 80, 0.1)';
            } else {
                label.style.borderColor = 'transparent';
                label.style.background = 'rgba(255,255,255,0.05)';
            }
        });
    });
}

// Confirm quick add subjects
function confirmQuickAdd() {
    const modal = document.getElementById('editGradesModal');
    if (!modal) return;
    
    const subjectsList = modal.querySelector('#subjectsList');
    const checkboxes = document.querySelectorAll('#quickSubjects input:checked');
    
    checkboxes.forEach(checkbox => {
        const subject = checkbox.value;
        const newRow = document.createElement('div');
        newRow.className = 'subject-row';
        newRow.style.cssText = 'display: flex; gap: 0.5rem; margin-bottom: 0.8rem; align-items: center; background: rgba(255,255,255,0.05); padding: 0.8rem; border-radius: 8px; border-left: 3px solid #82b2ff;';
        newRow.innerHTML = `
            <div style="flex: 1; display: flex; flex-direction: column; gap: 0.3rem;">
                <label style="font-size: 0.75rem; color: #82b2ff; font-weight: 600;">SUBJECT NAME</label>
                <input type="text" value="${subject}" class="subject-title" placeholder="Subject name" 
                    style="flex: 1; padding: 0.6rem; border-radius: 6px; border: 1px solid #82b2ff33; background: #fff; color: #222; font-size: 1rem;">
            </div>
            <div style="width: 120px; display: flex; flex-direction: column; gap: 0.3rem;">
                <label style="font-size: 0.75rem; color: #a5d6a7; font-weight: 600;">GRADE</label>
                <input type="number" class="subject-grade" placeholder="0-100" min="0" max="100" 
                    style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #82b2ff33; background: #fff; color: #222; font-size: 1.1rem; font-weight: bold; text-align: center;">
            </div>
            <button onclick="this.closest('.subject-row').remove(); updateGradeStats();" 
                style="background: linear-gradient(135deg, #f44336, #c62828); padding: 0.6rem 0.8rem; border-radius: 6px; border: none; color: white; cursor: pointer; margin-top: 1.2rem;"
                title="Remove subject">
                <i class="fas fa-trash"></i>
            </button>
        `;
        subjectsList.appendChild(newRow);
        
        // Add listener for stats update
        newRow.querySelector('.subject-grade').addEventListener('input', updateGradeStats);
    });
    
    // Close quick add modal
    document.body.querySelectorAll('.modal').forEach(m => {
        if (m.id !== 'editGradesModal') m.remove();
    });
    
    updateGradeStats();
    showNotif(`${checkboxes.length} subject(s) added`);
}

// Add Subject Row
function addSubjectRow() {
    const subjectsList = document.getElementById('subjectsList');
    
    // Remove "no subjects" message if present
    const noSubjectsMsg = subjectsList.querySelector('div[style*="dashed"]');
    if (noSubjectsMsg) noSubjectsMsg.remove();
    
    const newRow = document.createElement('div');
    newRow.className = 'subject-row';
    newRow.style.cssText = 'display: flex; gap: 0.5rem; margin-bottom: 0.8rem; align-items: center; background: rgba(255,255,255,0.05); padding: 0.8rem; border-radius: 8px; border-left: 3px solid #82b2ff;';
    newRow.innerHTML = `
        <div style="flex: 1; display: flex; flex-direction: column; gap: 0.3rem;">
            <label style="font-size: 0.75rem; color: #82b2ff; font-weight: 600;">SUBJECT NAME</label>
            <input type="text" class="subject-title" placeholder="e.g., Mathematics" 
                style="flex: 1; padding: 0.6rem; border-radius: 6px; border: 1px solid #82b2ff33; background: #fff; color: #222; font-size: 1rem;">
        </div>
        <div style="width: 120px; display: flex; flex-direction: column; gap: 0.3rem;">
            <label style="font-size: 0.75rem; color: #a5d6a7; font-weight: 600;">GRADE</label>
            <input type="number" class="subject-grade" placeholder="0-100" min="0" max="100" 
                style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #82b2ff33; background: #fff; color: #222; font-size: 1.1rem; font-weight: bold; text-align: center;">
        </div>
        <button onclick="this.closest('.subject-row').remove(); updateGradeStats();" 
            style="background: linear-gradient(135deg, #f44336, #c62828); padding: 0.6rem 0.8rem; border-radius: 6px; border: none; color: white; cursor: pointer; margin-top: 1.2rem;"
            title="Remove subject">
            <i class="fas fa-trash"></i>
        </button>
    `;
    subjectsList.appendChild(newRow);
    
    // Focus on the subject name input
    newRow.querySelector('.subject-title').focus();
    
    // Add listener for stats update
    newRow.querySelector('.subject-grade').addEventListener('input', updateGradeStats);
    
    updateGradeStats();
}

// Save Grades
async function saveGrades(studentId) {
    const subjectRows = document.querySelectorAll('.subject-row');
    const subjects = Array.from(subjectRows).map(row => ({
        title: row.querySelector('.subject-title').value.trim(),
        grade: row.querySelector('.subject-grade').value
    })).filter(sub => sub.title && sub.grade);
    
    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'updateGrades',
                studentId: studentId,
                subjects: subjects
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotif('Grades saved successfully!');
            document.getElementById('editGradesModal').remove();
            renderAdminDashboard();
        } else {
            showNotif('Error saving grades');
        }
    } catch (error) {
        console.error('Error saving grades:', error);
        showNotif('Error saving grades');
    }
}

// Delete Student
async function deleteStudent(studentId) {
    if (!confirm('Are you sure you want to delete this student?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'deleteStudent',
                studentId: studentId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotif('Student deleted successfully');
            renderAdminDashboard();
        } else {
            showNotif('Error deleting student');
        }
    } catch (error) {
        console.error('Error deleting student:', error);
        showNotif('Error deleting student');
    }
}

// Logout Function
function logout() {
    currentUser = null;
    currentRole = null;
    showSection('home');
    dashboardContent.innerHTML = '';
    dashActions.innerHTML = '';
    showNotif('Logged out successfully');
}

// Make functions globally available
window.showAddStudentModal = showAddStudentModal;
window.editStudentGrades = editStudentGrades;
window.deleteStudent = deleteStudent;
window.saveGrades = saveGrades;
window.addSubjectRow = addSubjectRow;
window.logout = logout;
window.copyToClipboard = copyToClipboard;
window.updateGradeStats = updateGradeStats;
window.addMultipleSubjects = addMultipleSubjects;
window.confirmQuickAdd = confirmQuickAdd;
window.loadStudentsList = loadStudentsList;

// New per-subject grading functions
window.addStudentRecord = addStudentRecord;
window.openSubjectsModal = openSubjectsModal;
window.addSubjectForStudent = addSubjectForStudent;
window.deleteSubject = deleteSubject;
window.editSubject = editSubject;

