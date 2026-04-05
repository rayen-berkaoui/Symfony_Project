import './bootstrap.js';
import './styles/app.css';
import './styles/travel-theme.css';

console.log('✅ Validation system loaded!');

// ============================================
// WAIT FOR DOM TO BE READY
// ============================================
document.addEventListener('DOMContentLoaded', () => {
	console.log('✅ DOM Ready - Initializing...');
	
	// ============================================
	// PASSWORD TOGGLE FUNCTIONALITY
	// ============================================
	document.querySelectorAll('.password-toggle').forEach((toggleBtn) => {
		console.log('✅ Found password toggle button');
		
		toggleBtn.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			const targetId = this.getAttribute('data-target');
			console.log('🔍 Target ID:', targetId);
			
			if (!targetId) {
				console.error('❌ No data-target attribute found');
				return;
			}
			
			const passwordInput = document.getElementById(targetId);
			console.log('🔍 Password input:', passwordInput);
			
			if (!passwordInput) {
				console.error('❌ No input found with ID:', targetId);
				return;
			}
			
			// Toggle the type
			const isPassword = passwordInput.type === 'password';
			passwordInput.type = isPassword ? 'text' : 'password';
			
			// Toggle the button class
			this.classList.toggle('is-visible', isPassword);
			
			// Update ARIA
			this.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
			this.setAttribute('aria-label', isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
			
			console.log('✅ Password toggled to:', passwordInput.type);
		});
	});
	
	// ============================================
	// VALIDATION FUNCTIONS
	// ============================================
	const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	const phoneRegex = /^[0-9\s\-\+\(\)]{6,20}$/;
	const alphaRegex = /^[a-zA-ZÀ-ÿ\s\-']+$/;
	
	function getFieldLabel(input) {
		if (input.dataset.label) {
			return input.dataset.label;
		}
		const label = input.closest('.form-row')?.querySelector('label');
		if (label) {
			return label.textContent.trim();
		}
		return 'Ce champ';
	}
	
	function setFieldError(input, message) {
		const formRow = input.closest('.form-row');
		if (!formRow) {
			console.warn('⚠️ No form-row found for input');
			return;
		}
		
		// Find or create error element
		let errorEl = formRow.querySelector('.field-error');
		if (!errorEl) {
			errorEl = document.createElement('div');
			errorEl.className = 'field-error';
			formRow.appendChild(errorEl);
		}
		
		if (message) {
			errorEl.textContent = message;
			errorEl.style.display = 'block';
			formRow.classList.add('is-invalid');
			formRow.classList.remove('is-valid');
			
			// Shake animation
			input.classList.add('shake');
			setTimeout(() => input.classList.remove('shake'), 500);
			
			console.log('❌ Validation error:', message);
		} else {
			errorEl.textContent = '';
			errorEl.style.display = 'none';
			formRow.classList.remove('is-invalid');
			formRow.classList.add('is-valid');
			
			setTimeout(() => formRow.classList.remove('is-valid'), 2000);
		}
	}
	
	function validateInput(input) {
		const rules = (input.dataset.validate || '').split('|').filter(Boolean);
		
		if (rules.length === 0 || input.disabled) {
			return true;
		}
		
		const label = getFieldLabel(input);
		const value = input.value.trim();
		
		console.log('🔍 Validating:', label, '| Value:', value, '| Rules:', rules);
		
		// Check each rule
		for (const rule of rules) {
			// Required check
			if (rule === 'required' && value === '') {
				setFieldError(input, `${label} est obligatoire.`);
				return false;
			}
			
			// Email check
			if (rule === 'email' && value !== '' && !emailRegex.test(value)) {
				setFieldError(input, `${label} doit être un email valide.`);
				return false;
			}
			
			// Phone check
			if (rule === 'phone' && value !== '') {
				if (!phoneRegex.test(value)) {
					setFieldError(input, `${label} invalide.`);
					return false;
				}
				const digitsOnly = value.replace(/\D/g, '');
				if (digitsOnly.length < 6 || digitsOnly.length > 15) {
					setFieldError(input, 'Le numero doit contenir entre 6 et 15 chiffres.');
					return false;
				}
			}
			
			// Alpha check (letters only)
			if (rule === 'alpha' && value !== '' && !alphaRegex.test(value)) {
				setFieldError(input, `${label} ne doit contenir que des lettres.`);
				return false;
			}
			
			// Numeric check
			if (rule === 'numeric' && value !== '' && !/^\d+$/.test(value)) {
				setFieldError(input, `${label} ne doit contenir que des chiffres.`);
				return false;
			}
			
			// Min length check
			if (rule.startsWith('minlength:')) {
				const min = parseInt(rule.split(':')[1]);
				if (value !== '' && value.length < min) {
					setFieldError(input, `${label} doit contenir au moins ${min} caractères.`);
					return false;
				}
			}
			
			// Max length check
			if (rule.startsWith('maxlength:')) {
				const max = parseInt(rule.split(':')[1]);
				if (value.length > max) {
					setFieldError(input, `${label} doit contenir au maximum ${max} caractères.`);
					return false;
				}
			}
		}
		
		// All rules passed
		setFieldError(input, '');
		console.log('✅ Validation passed:', label);
		return true;
	}
	
	function validateForm(form) {
		let isValid = true;
		let firstInvalidInput = null;
		
		console.log('🔍 Validating form...');
		
		form.querySelectorAll('[data-validate]').forEach((input) => {
			if (!validateInput(input)) {
				isValid = false;
				if (!firstInvalidInput) {
					firstInvalidInput = input;
				}
			}
		});
		
		if (!isValid && firstInvalidInput) {
			firstInvalidInput.focus();
			console.log('❌ Form validation failed');
		} else {
			console.log('✅ Form validation passed');
		}
		
		return isValid;
	}
	
	// ============================================
	// PASSWORD STRENGTH INDICATOR
	// ============================================
	function updatePasswordStrength(input) {
		const password = input.value;
		const strengthBarId = `password-strength-${input.id}`;
		const strengthBar = document.getElementById(strengthBarId);
		
		if (!strengthBar) return;
		
		const indicator = strengthBar.querySelector('.strength-indicator');
		const text = strengthBar.querySelector('.strength-text');
		
		if (!password) {
			strengthBar.style.display = 'none';
			return;
		}
		
		strengthBar.style.display = 'block';
		
		let strength = 0;
		
		// Length
		if (password.length >= 6) strength += 25;
		if (password.length >= 10) strength += 25;
		
		// Character variety
		if (/[a-z]/.test(password)) strength += 15;
		if (/[A-Z]/.test(password)) strength += 15;
		if (/[0-9]/.test(password)) strength += 10;
		if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 10;
		
		let strengthText, strengthColor;
		if (strength < 30) {
			strengthText = 'Faible';
			strengthColor = '#ff5c5c';
		} else if (strength < 60) {
			strengthText = 'Moyen';
			strengthColor = '#ffa500';
		} else if (strength < 80) {
			strengthText = 'Bon';
			strengthColor = '#5c9cff';
		} else {
			strengthText = 'Excellent';
			strengthColor = '#5cff5c';
		}
		
		indicator.style.width = `${strength}%`;
		indicator.style.backgroundColor = strengthColor;
		text.textContent = strengthText;
		text.style.color = strengthColor;
	}
	
	// ============================================
	// FORM INITIALIZATION
	// ============================================
	document.querySelectorAll('form.js-validate').forEach((form) => {
		console.log('✅ Initializing form validation');
		
		// Submit handler
		form.addEventListener('submit', (event) => {
			console.log('📋 Form submitted');
			
			if (!validateForm(form)) {
				event.preventDefault();
				event.stopPropagation();
				
				// Shake animation
				form.classList.add('form-shake');
				setTimeout(() => form.classList.remove('form-shake'), 500);
				
				console.log('❌ Form submission prevented');
				return false;
			}
			
			// Show loading state
			const submitBtn = form.querySelector('button[type="submit"]');
			if (submitBtn && !submitBtn.classList.contains('btn-loading')) {
				submitBtn.classList.add('btn-loading');
				submitBtn.disabled = true;
			}
		});
		
		// Input handlers
		form.querySelectorAll('[data-validate]').forEach((input) => {
			// Focus handler
			input.addEventListener('focus', () => {
				input.closest('.form-row')?.classList.add('is-focused');
			});
			
			// Blur handler
			input.addEventListener('blur', () => {
				input.closest('.form-row')?.classList.remove('is-focused');
				validateInput(input);
			});
			
			// Input handler (with debounce)
			let inputTimeout = null;
			input.addEventListener('input', () => {
				clearTimeout(inputTimeout);
				
				// Update password strength if applicable
				if (input.dataset.passwordStrength === 'true') {
					updatePasswordStrength(input);
				}
				
				// Debounced validation
				if (input.closest('.form-row')?.classList.contains('is-invalid')) {
					inputTimeout = setTimeout(() => validateInput(input), 300);
				}
				
				// Typing indicator
				input.classList.add('is-typing');
				setTimeout(() => input.classList.remove('is-typing'), 300);
			});
			
			// Restrict numeric inputs
			if (input.dataset.validate?.includes('numeric') || input.dataset.validate?.includes('phone')) {
				input.addEventListener('keypress', (e) => {
					if (!/[0-9\+\-\(\)\s]/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
						e.preventDefault();
						input.classList.add('invalid-key');
						setTimeout(() => input.classList.remove('invalid-key'), 200);
					}
				});
			}
			
			// Restrict alpha inputs
			if (input.dataset.validate?.includes('alpha')) {
				input.addEventListener('keypress', (e) => {
					if (!/[a-zA-ZÀ-ÿ\s\-']/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
						e.preventDefault();
						input.classList.add('invalid-key');
						setTimeout(() => input.classList.remove('invalid-key'), 200);
					}
				});
			}
		});
	});
	
	// ============================================
	// PREVENT NUMBER INPUT SCROLL
	// ============================================
	document.addEventListener('wheel', (event) => {
		if (event.target instanceof HTMLInputElement && event.target.type === 'number' && event.target === document.activeElement) {
			event.preventDefault();
		}
	}, { passive: false });
	
	console.log('✅ All systems initialized!');
});
