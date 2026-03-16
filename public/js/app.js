document.addEventListener('DOMContentLoaded', () => {
    
    // === REGISTRACIJA ===
    const form = document.getElementById('registerForm');
    const errorMessage = document.getElementById('error-message');

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            console.log('Form submitted');

            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const csrfToken = document.querySelector('input[name="_csrf_token"]').value || null;

            // Klijentska validacija
            const usernameRegex = /^[a-zA-Z0-9_-]{3,20}$/;
            if (!usernameRegex.test(username)) {
                errorMessage.textContent = 'Korisničko ime nije validno. Dozvoljeni su alfanumerički karakteri, donja crta i crtica, dužine 3-20 karaktera.';
                errorMessage.classList.remove('hidden');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errorMessage.textContent = 'Nevalidna email adresa.';
                errorMessage.classList.remove('hidden');
                return;
            }
            if (password.length < 8 || !/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/.test(password)) {
                errorMessage.textContent = 'Lozinka mora imati najmanje 8 karaktera i sadržati slova i brojeve.';
                errorMessage.classList.remove('hidden');
                return;
            }

            errorMessage.classList.add('hidden');

            const data = { username, email, password };

            try {
                const response = await fetch('/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok) {
                    window.location.href = '/register/success';
                } else {
                    errorMessage.textContent = result.error || 'Nepoznata greška';
                    errorMessage.classList.remove('hidden');
                }
            } catch (error) {
                errorMessage.textContent = 'Greška prilikom komunikacije sa serverom';
                errorMessage.classList.remove('hidden');
            }
        });
    }

         // === LOAD MORE KOMENTARA ===
    // Koristimo event delegation jer se dugme nalazi u dinamičkom delu stranice
    document.addEventListener('click', async (e) => {
        if (e.target && e.target.id === 'load-more-btn') {
            const btn = e.target;
            const originalText = btn.textContent;

            btn.textContent = 'Učitavam...';
            btn.disabled = true;

            let offset = parseInt(btn.dataset.offset || '3');
            const limit = 3;

            try {
                const response = await fetch(`/comments/load-more?offset=${offset}`);
                
                if (!response.ok) {
                    throw new Error('Server error');
                }

                const data = await response.json();

                if (data.comments && data.comments.length > 0) {
                    const container = document.getElementById('comments-container');

                    data.comments.forEach(comment => {
                        const card = document.createElement('div');
                        card.className = 'bg-white rounded-2xl shadow p-6';
                        card.innerHTML = `
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center font-semibold text-lg">
                                    ${comment.name ? comment.name[0].toUpperCase() : 'K'}
                                </div>
                                <div>
                                    <p class="font-semibold">${comment.name || 'Anonimni kupac'}</p>
                                    <p class="text-xs text-gray-500">
                                        ${new Date(comment.created_at).toLocaleDateString('sr-RS')}
                                    </p>
                                </div>
                            </div>
                            <p class="text-gray-700 leading-relaxed">"${comment.text}"</p>
                        `;
                        container.appendChild(card);
                    });

                    offset += limit;
                    btn.dataset.offset = offset;

                    if (!data.hasMore) {
                        btn.style.display = 'none';
                    }
                } else {
                    btn.style.display = 'none';
                }
            } catch (error) {
                console.error('Greška pri učitavanju komentara:', error);
                btn.textContent = 'Greška – pokušaj ponovo';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2500);
            } finally {
                if (btn.style.display !== 'none') {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            }
        }
    });
});