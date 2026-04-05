import './stimulus_bootstrap.js';
import './styles/app_front.css';
import './styles/travel-theme.css';

console.log('✅ Validation system loaded!');

// ============================================
// VALIDATION REGULAR EXPRESSIONS
// ============================================
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const phoneRegex = /^[0-9\s\-\+\(\)]{6,20}$/;
const alphaRegex = /^[a-zA-ZÀ-ÿ\s\-']+$/;

// ============================================
// PASSWORD TOGGLE FUNCTIONALITY
// ============================================
document.addEventListener('DOMContentLoaded', () => {
	const initPasswordToggles = () => {
		// Find all password fields inside .password-field containers
		document.querySelectorAll('.password-field').forEach((container) => {
			// Skip if already initialized
			if (container.dataset.toggleInitialized === 'true') {
				return;
			}
			container.dataset.toggleInitialized = 'true';
			
			const input = container.querySelector('input[type="password"]');
			if (!input) return;
			
			// Create the toggle button
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'password-toggle';
			button.setAttribute('aria-pressed', 'false');
			button.setAttribute('aria-label', 'Afficher le mot de passe');
			button.innerHTML = `
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
					<circle cx="12" cy="12" r="3"/>
				</svg>
			`;
			
			// Add click handler
			button.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				
				// Toggle password visibility
				const isCurrentlyPassword = input.type === 'password';
				input.type = isCurrentlyPassword ? 'text' : 'password';
				
				// Update button aria attributes
				button.setAttribute('aria-pressed', isCurrentlyPassword ? 'true' : 'false');
				button.setAttribute(
					'aria-label',
					isCurrentlyPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'
				);
				
				// Add animation feedback
				button.style.transform = 'translateY(-50%) scale(0.9)';
				setTimeout(() => {
					button.style.transform = 'translateY(-50%)';
				}, 150);
			});
			
			// Append button to container
			container.appendChild(button);
		});
	};
	
	// Initialize immediately
	initPasswordToggles();
	
	// Re-initialize on dynamic content changes
	const observer = new MutationObserver(() => {
		initPasswordToggles();
	});
	
	observer.observe(document.body, {
		childList: true,
		subtree: true
	});
});

// ============================================
// VALIDATION REGULAR EXPRESSIONS
// ============================================
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const phoneRegex = /^[0-9\s\-\+\(\)]{6,20}$/;
const alphaRegex = /^[a-zA-ZÀ-ÿ\s\-']+$/;
const passwordStrengthRegex = {
	hasLower: /[a-z]/,
	hasUpper: /[A-Z]/,
	hasNumber: /[0-9]/,
	hasSpecial: /[!@#$%^&*(),.?":{}|<>]/
};

// ============================================
// VALIDATION HELPER FUNCTIONS
// ============================================
const getFieldLabel = (input) => {
	if (input.dataset.label) {
		return input.dataset.label;
	}
	const rowLabel = input.closest('.form-row')?.querySelector('label');
	if (rowLabel) {
		return rowLabel.textContent?.trim() || 'Ce champ';
	}
	return input.name || 'Ce champ';
};

const setFieldError = (input, message) => {
	const row = input.closest('.form-row') || input.parentElement;
	if (!row) {
		return;
	}
	let errorEl = row.querySelector('.field-error');
	if (!errorEl) {
		errorEl = document.createElement('div');
		errorEl.className = 'field-error';
		row.appendChild(errorEl);
	}
	if (message) {
		errorEl.textContent = message;
		row.classList.add('is-invalid');
		// Shake animation on error
		input.classList.add('shake');
		setTimeout(() => input.classList.remove('shake'), 500);
	} else {
		errorEl.textContent = '';
		row.classList.remove('is-invalid');
		input.classList.add('success-pulse');
		setTimeout(() => input.classList.remove('success-pulse'), 600);
	}
};

const setFieldSuccess = (input) => {
	const row = input.closest('.form-row') || input.parentElement;
	if (!row) return;
	
	row.classList.remove('is-invalid');
	row.classList.add('is-valid');
	
	const errorEl = row.querySelector('.field-error');
	if (errorEl) {
		errorEl.textContent = '';
	}
	
	setTimeout(() => {
		row.classList.remove('is-valid');
	}, 2000);
};

const validateInput = (input) => {
	const rules = (input.dataset.validate || '').split('|').filter(Boolean);
	if (rules.length === 0 || input.disabled) {
		return true;
	}
	const label = getFieldLabel(input);
	const value = (input.value || '').trim();

	for (const rule of rules) {
		if (rule === 'required' && value === '') {
			setFieldError(input, `${label} est obligatoire.`);
			return false;
		}

		if (rule === 'email' && value !== '' && !emailRegex.test(value)) {
			setFieldError(input, 'Email invalide.');
			return false;
		}

		if (rule === 'numeric' && value !== '' && !/^\d+$/.test(value)) {
			setFieldError(input, `${label} doit contenir uniquement des chiffres.`);
			return false;
		}

		if (rule === 'phone' && value !== '') {
			if (!phoneRegex.test(value)) {
				setFieldError(input, 'Numero de telephone invalide.');
				return false;
			}
			const digitsOnly = value.replace(/\D/g, '');
			if (digitsOnly.length < 6 || digitsOnly.length > 15) {
				setFieldError(input, 'Le numero doit contenir entre 6 et 15 chiffres.');
				return false;
			}
		}

		if (rule === 'alpha' && value !== '' && !alphaRegex.test(value)) {
			setFieldError(input, `${label} ne doit contenir que des lettres.`);
			return false;
		}

		if (rule.startsWith('minlength:')) {
			const min = Number(rule.split(':')[1]);
			if (value !== '' && value.length < min) {
				setFieldError(input, `${label} doit contenir au moins ${min} caracteres.`);
				return false;
			}
		}

		if (rule.startsWith('maxlength:')) {
			const max = Number(rule.split(':')[1]);
			if (value.length > max) {
				setFieldError(input, `${label} doit contenir au maximum ${max} caracteres.`);
				return false;
			}
		}

		if (rule === 'password_strength' && value !== '') {
			if (value.length < 6) {
				setFieldError(input, 'Le mot de passe doit contenir au moins 6 caracteres.');
				return false;
			}
			if (!/[a-z]/.test(value) || !/[A-Z]/.test(value) || !/[0-9]/.test(value)) {
				// Optional: strong password validation (commented out for flexibility)
				// setFieldError(input, 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.');
				// return false;
			}
		}
	}

	setFieldError(input, '');
	if (value !== '') {
		setFieldSuccess(input);
	}
	return true;
};

const validateForm = (form) => {
	let valid = true;
	let firstInvalid = null;

	form.querySelectorAll('[data-validate]').forEach((input) => {
		const ok = validateInput(input);
		if (!ok) {
			valid = false;
			if (!firstInvalid) {
				firstInvalid = input;
			}
		}
	});

	if (!valid && firstInvalid) {
		firstInvalid.focus();
	}

	return valid;
};

// ============================================
// FORM VALIDATION INITIALIZATION
// ============================================
document.querySelectorAll('form.js-validate').forEach((form) => {
	// Submit handler
	form.addEventListener('submit', (event) => {
		if (!validateForm(form)) {
			event.preventDefault();
			event.stopPropagation();
			
			// Add form shake animation
			form.classList.add('form-shake');
			setTimeout(() => form.classList.remove('form-shake'), 500);
		}
	});

	// Input validation handlers
	form.querySelectorAll('[data-validate]').forEach((input) => {
		// Add focus animation
		input.addEventListener('focus', () => {
			input.closest('.form-row')?.classList.add('is-focused');
		});
		
		input.addEventListener('blur', () => {
			input.closest('.form-row')?.classList.remove('is-focused');
			validateInput(input);
		});
		
		// Real-time validation with debouncing
		let timeout = null;
		input.addEventListener('input', () => {
			clearTimeout(timeout);
			
			// Clear error state immediately for better UX
			if (input.closest('.form-row')?.classList.contains('is-invalid')) {
				timeout = setTimeout(() => validateInput(input), 300);
			}
			
			// Add typing indicator
			input.classList.add('is-typing');
			clearTimeout(input.typingTimeout);
			input.typingTimeout = setTimeout(() => {
				input.classList.remove('is-typing');
			}, 300);
			
			// Password strength indicator
			if (input.dataset.passwordStrength === 'true') {
				updatePasswordStrength(input);
			}
		});
		
		// Input restrictions based on validation type
		if (input.dataset.validate?.includes('numeric') || input.dataset.validate?.includes('phone') || input.type === 'number') {
			input.addEventListener('keypress', (e) => {
				if (!/[0-9\+\-\(\)\s]/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
					e.preventDefault();
					// Visual feedback for invalid key
					input.classList.add('invalid-key');
					setTimeout(() => input.classList.remove('invalid-key'), 200);
				}
			});
			
			// Prevent paste of non-numeric content
			input.addEventListener('paste', (e) => {
				const pastedData = e.clipboardData.getData('text');
				if (!/^[0-9\+\-\(\)\s]*$/.test(pastedData)) {
					e.preventDefault();
				}
			});
		}
		
		if (input.dataset.validate?.includes('alpha')) {
			input.addEventListener('keypress', (e) => {
				if (!/[a-zA-ZÀ-ÿ\s\-']/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
					e.preventDefault();
					// Visual feedback for invalid key
					input.classList.add('invalid-key');
					setTimeout(() => input.classList.remove('invalid-key'), 200);
				}
			});
			
			// Prevent paste of non-alpha content
			input.addEventListener('paste', (e) => {
				const pastedData = e.clipboardData.getData('text');
				if (!/^[a-zA-ZÀ-ÿ\s\-']*$/.test(pastedData)) {
					e.preventDefault();
				}
			});
		}
	});
	
	// Add loading animation on valid submit
	form.addEventListener('submit', (event) => {
		if (validateForm(form)) {
			const submitBtn = form.querySelector('button[type="submit"]');
			if (submitBtn && !submitBtn.classList.contains('btn-loading')) {
				submitBtn.classList.add('btn-loading');
				submitBtn.disabled = true;
			}
		}
	});
});

// ============================================
// PASSWORD STRENGTH INDICATOR
// ============================================
const updatePasswordStrength = (input) => {
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
	let strengthText = '';
	let strengthColor = '';
	
	// Length check
	if (password.length >= 6) strength += 25;
	if (password.length >= 10) strength += 25;
	
	// Character variety
	if (/[a-z]/.test(password)) strength += 15;
	if (/[A-Z]/.test(password)) strength += 15;
	if (/[0-9]/.test(password)) strength += 10;
	if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 10;
	
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
};

document.addEventListener(
	'wheel',
	(event) => {
		const target = event.target;
		if (target instanceof HTMLInputElement && target.type === 'number' && target === document.activeElement) {
			event.preventDefault();
		}
	},
	{ passive: false }
);
