/**
 * ==========================================================================
 * BOLIDENT LANDING PAGE - MODERN & INTERACTIVE JAVASCRIPT CONTROLLERS
 * ==========================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    initNavbarScroll();
    initMobileNav();
    initWhatsAppSimulator();
    initOdontogramaInteractive();
    initBenefitsSimulator();
    initModuleTabs();
    initPricingToggle();
    initFaqAccordion();
    initModalDemo();
});

/* ==========================================================================
   1. NAVBAR SCROLL EFFECT & MOBILE MENU TOGGLE
   ========================================================================== */
function initNavbarScroll() {
    const navbar = document.getElementById('mainNavbar');
    if (!navbar) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 30) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

function initMobileNav() {
    const toggleBtn = document.getElementById('mobileNavToggle');
    const navMenu = document.getElementById('navMenu');
    if (!toggleBtn || !navMenu) return;

    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        navMenu.classList.toggle('open');
        const isOpen = navMenu.classList.contains('open');
        toggleBtn.innerHTML = isOpen ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        toggleBtn.setAttribute('aria-expanded', isOpen);
    });

    // Cerrar al hacer clic en cualquier enlace
    const navLinks = navMenu.querySelectorAll('a');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('open');
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            toggleBtn.setAttribute('aria-expanded', 'false');
        });
    });

    // Cerrar al hacer clic afuera
    document.addEventListener('click', (e) => {
        if (navMenu.classList.contains('open') && !navMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
            navMenu.classList.remove('open');
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    });
}

/* ==========================================================================
   2. INTERACTIVE WHATSAPP BOT SIMULATOR (PERSONALIZED MULTI-STEP FLOW)
   ========================================================================== */
function initWhatsAppSimulator() {
    const chatBody = document.getElementById('waChatBody');
    const chipButtons = document.querySelectorAll('.wa-chip-btn');
    if (!chatBody) return;

    let isTyping = false;
    let patientName = '';

    chipButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            if (isTyping) return;
            const scenarioKey = btn.getAttribute('data-scenario');

            isTyping = true;
            chipButtons.forEach(b => b.style.opacity = '0.35');
            chipButtons.forEach(b => b.style.pointerEvents = 'none');

            if (scenarioKey === 'agendar') {
                await runAgendarFlow();
            } else if (scenarioKey === 'precios') {
                await runSimpleFlow(
                    "¿Qué precio tienen los tratamientos de limpieza y ortodoncia?",
                    [
                        "✨ En el consultorio de la *Dra. Tatiana Ruiz* contamos con tratamientos garantizados:\n\n🦷 *Limpieza Profunda con Ultrasonido:* Bs. 180\n🦷 *Ortodoncia (Frenillos / Alineadores):* Desde Bs. 1,800 (con facilidades de pago en cuotas)\n🦷 *Blanqueamiento Dental:* Bs. 450\n\n¿Te gustaría reservar una cita de evaluación?"
                    ]
                );
            } else if (scenarioKey === 'confirmar') {
                await runSimpleFlow(
                    "Sí, confirmo mi cita médica de mañana",
                    [
                        "✅ ¡Excelente! Tu cita para mañana a las *10:00 am* con la *Dra. Tatiana Ruiz* ha quedado *CONFIRMADA*. 🎉",
                        "📍 Te esperamos en el consultorio de la *Dra. Tatiana Ruiz*. Te recomendamos llegar 5 minutos antes. ¡Será un gusto atenderte! 🦷✨"
                    ]
                );
            } else if (scenarioKey === 'doctores') {
                await runSimpleFlow(
                    "¿Qué especialidades atienden?",
                    [
                        "🩺 Especialidades de la Dra. Tatiana Ruiz:\n\n1. *Rehabilitación Oral*\n2. *Estética Dental*\n3. *Ortodoncia*\n\n¿Deseas reservar una cita de evaluación?"
                    ]
                );
            }

            isTyping = false;
            chipButtons.forEach(b => b.style.opacity = '1');
            chipButtons.forEach(b => b.style.pointerEvents = 'auto');
        });
    });

    // ==========================================
    // PERSONALIZED MULTI-STEP AGENDAR FLOW
    // ==========================================
    async function runAgendarFlow() {
        // Step 1: Patient sends greeting
        appendMessage('outgoing', "Hola, buenas tardes, quisiera agendar una cita dental por favor 😊");
        scrollChatToBottom();

        await wait(500);
        let typing = appendTypingIndicator();
        scrollChatToBottom();
        await wait(1200);
        typing.remove();

        appendMessage('incoming', "¡Hola! 👋✨ Gracias por comunicarte con la *Dra. Tatiana Ruiz*. Con gusto te ayudo a reservar tu consulta.\n\n¿Me podrías indicar tu *nombre completo*, por favor?");
        scrollChatToBottom();

        // Step 2: Patient gives their name
        await wait(1400);
        patientName = 'María García';
        appendMessage('outgoing', "Sí, mi nombre es María García");
        scrollChatToBottom();

        await wait(400);
        typing = appendTypingIndicator();
        scrollChatToBottom();
        await wait(1000);
        typing.remove();

        appendMessage('incoming', "¡Mucho gusto, *María*! 😊🦷\n\n¿Qué tipo de consulta o tratamiento necesitas?\n\n1️⃣ Limpieza Dental\n2️⃣ Valoración General\n3️⃣ Control de Ortodoncia\n4️⃣ Curación / Dolor de Muela");
        scrollChatToBottom();

        // Step 3: Patient selects treatment
        await wait(1600);
        appendMessage('outgoing', "Limpieza dental, por favor 🙏");
        scrollChatToBottom();

        await wait(400);
        typing = appendTypingIndicator();
        scrollChatToBottom();
        await wait(1200);
        typing.remove();

        appendMessage('incoming', "¡Perfecto, María! Para tu *Limpieza Dental* con la *Dra. Laura Rios*, tenemos disponible para mañana:\n\n⏰ *09:30 am*\n⏰ *11:00 am*\n⏰ *14:30 pm*\n\n¿Cuál horario te queda más cómodo?");
        scrollChatToBottom();

        // Step 4: Patient picks time
        await wait(1500);
        appendMessage('outgoing', "El de las 14:30 por favor 👍");
        scrollChatToBottom();

        await wait(400);
        typing = appendTypingIndicator();
        scrollChatToBottom();
        await wait(1000);
        typing.remove();

        appendMessage('incoming', "✅ ¡Listo, *María García*! Tu cita ha sido agendada con éxito:\n\n📅 *Fecha:* Mañana, miércoles 2 de septiembre\n⏰ *Hora:* 14:30 pm\n🦷 *Tratamiento:* Limpieza Dental\n👩‍⚕️ *Doctora:* Dra. Tatiana Ruiz\n📍 *Lugar:* Consultorio Dra. Tatiana Ruiz\n\nTe enviaremos un recordatorio antes de tu cita. ¡Te esperamos con gusto! 🦷✨");
        scrollChatToBottom();

        // Step 5: Sync with the monthly calendar!
        await wait(600);
        syncCalendarNewAppointment();
    }

    // ==========================================
    // SIMPLE SINGLE-STEP FLOWS
    // ==========================================
    async function runSimpleFlow(userMsg, botReplies) {
        appendMessage('outgoing', userMsg);
        scrollChatToBottom();

        await wait(500);
        const typing = appendTypingIndicator();
        scrollChatToBottom();

        for (let i = 0; i < botReplies.length; i++) {
            await wait(1200);
            if (i === 0) typing.remove();
            appendMessage('incoming', botReplies[i]);
            scrollChatToBottom();
        }
    }

    // ==========================================
    // CALENDAR SYNC: Inject new appointment into monthly grid
    // ==========================================
    function syncCalendarNewAppointment() {
        // Target: Sep 2 day cell (second day in the grid after Aug 31)
        const dayCells = document.querySelectorAll('.sys-cal-day');
        let targetDay = null;

        // Find the day cell for "2" (September 2)
        for (const cell of dayCells) {
            const numEl = cell.querySelector('.sys-day-num');
            if (numEl && numEl.textContent.trim() === '2' && !cell.classList.contains('dim')) {
                targetDay = cell;
                break;
            }
        }

        if (!targetDay) return;

        // Create glowing bot-booked appointment chip
        const newAppt = document.createElement('div');
        newAppt.className = 'sys-appt bot-booked-appt';
        newAppt.innerHTML = '<i class="fab fa-whatsapp" style="font-size: 0.5rem;"></i> María García <small>14:30</small>';
        newAppt.style.opacity = '0';
        newAppt.style.transform = 'translateY(4px)';

        // Insert before the "+1 más" link if present, otherwise at the end
        const moreLink = targetDay.querySelector('.sys-more-link');
        if (moreLink) {
            targetDay.insertBefore(newAppt, moreLink);
        } else {
            targetDay.appendChild(newAppt);
        }

        // Animate in
        requestAnimationFrame(() => {
            newAppt.style.transition = 'all 0.4s ease';
            newAppt.style.opacity = '1';
            newAppt.style.transform = 'translateY(0)';
        });

        // Flash highlight on the day cell
        targetDay.style.transition = 'background 0.3s ease';
        targetDay.style.background = 'rgba(14, 165, 233, 0.15)';
        targetDay.style.borderColor = 'rgba(56, 189, 248, 0.4)';
        setTimeout(() => {
            targetDay.style.background = '';
            targetDay.style.borderColor = '';
        }, 2500);
    }

    function appendMessage(type, text) {
        const bubble = document.createElement('div');
        bubble.className = `wa-bubble ${type}`;
        
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        let formattedText = text
            .replace(/\n/g, '<br>')
            .replace(/\*(.*?)\*/g, '<strong>$1</strong>');

        bubble.innerHTML = `${formattedText} <span class="wa-time">${timeStr}</span>`;
        chatBody.appendChild(bubble);
        return bubble;
    }

    function appendTypingIndicator() {
        const ind = document.createElement('div');
        ind.className = 'wa-typing-indicator';
        ind.innerHTML = `
            <div class="wa-typing-dot"></div>
            <div class="wa-typing-dot"></div>
            <div class="wa-typing-dot"></div>
        `;
        chatBody.appendChild(ind);
        return ind;
    }

    function scrollChatToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

/* ==========================================================================
   3. INTERACTIVE ODONTOGRAMA VISUALIZER (ANATOMICAL FDI SYSTEM)
   ========================================================================== */
function initOdontogramaInteractive() {
    let selectedCond = 'caries';
    let applicationMode = 'superficie'; // 'superficie' | 'completo'

    const condiciones = {
        sano: { nombre: 'Sano / Limpio', color: '#FFFFFF' },
        caries: { nombre: 'Caries Dental', color: '#FF4444' },
        obturacion: { nombre: 'Obturación (Resina)', color: '#4A90D9' },
        corona: { nombre: 'Corona Dental', color: '#FFD700' },
        extraccion: { nombre: 'Extracción', color: '#FF0000' },
        ausente: { nombre: 'Pieza Ausente', color: '#64748B' },
        endodoncia: { nombre: 'Endodoncia', color: '#9B59B6' },
        protesis: { nombre: 'Prótesis', color: '#27AE60' },
        implante: { nombre: 'Implante', color: '#3498DB' }
    };

    const surfaceNames = {
        V: 'Vestibular',
        D: 'Distal',
        L: 'Lingual/Palatino',
        M: 'Mesial',
        O: 'Oclusal'
    };

    // Paleta de condiciones
    const condButtons = document.querySelectorAll('.odonto-cond-btn');
    condButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            condButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedCond = btn.getAttribute('data-condicion');
            
            const condName = condiciones[selectedCond]?.nombre || selectedCond;
            showLandingOdontoFeedback(`Diagnóstico seleccionado: ${condName}`);
        });
    });

    // Switch Dentition Tabs (Adulto vs Pediatrico)
    window.switchLandingDentition = function(tipo) {
        document.querySelectorAll('.odonto-tab-btn').forEach(b => b.classList.remove('active'));
        const tabAdulto = document.getElementById('landing-tab-adulto');
        const tabPediatrico = document.getElementById('landing-tab-pediatrico');
        const viewAdulto = document.getElementById('landing-viewport-adulto');
        const viewPediatrico = document.getElementById('landing-viewport-pediatrico');

        if (tipo === 'adulto') {
            if (tabAdulto) tabAdulto.classList.add('active');
            if (viewAdulto) viewAdulto.style.display = 'block';
            if (viewPediatrico) viewPediatrico.style.display = 'none';
            showLandingOdontoFeedback("Vista: Dentición Adulto (32 Dientes FDI 11-48)");
        } else {
            if (tabPediatrico) tabPediatrico.classList.add('active');
            if (viewAdulto) viewAdulto.style.display = 'none';
            if (viewPediatrico) viewPediatrico.style.display = 'block';
            showLandingOdontoFeedback("Vista: Dentición Infantil Decidua (20 Dientes FDI 51-85)");
        }
    };

    // Switch Application Mode (Superficie vs Completo)
    window.setLandingApplicationMode = function(modo) {
        applicationMode = modo;
        document.querySelectorAll('.odonto-mode-btn').forEach(b => b.classList.remove('active'));
        const btnSup = document.getElementById('landing-mode-superficie');
        const btnComp = document.getElementById('landing-mode-completo');

        if (modo === 'superficie') {
            if (btnSup) btnSup.classList.add('active');
            showLandingOdontoFeedback("Modo: Pinta cada cara individual (V, D, L, M, O)");
        } else {
            if (btnComp) btnComp.classList.add('active');
            showLandingOdontoFeedback("Modo: Aplica diagnóstico a todo el diente");
        }
    };

    // Event listener para clics en dientes y caras FDI
    document.addEventListener('click', (e) => {
        const surfEl = e.target.closest('.fdi-surface');
        const toothWrapper = e.target.closest('.tooth-wrapper');

        if (!toothWrapper) return;
        const diente = toothWrapper.getAttribute('data-diente');
        const condObj = condiciones[selectedCond];
        if (!condObj) return;

        const box = toothWrapper.querySelector('.tooth-anatomical-container');

        if (applicationMode === 'completo') {
            // Aplicar a todas las 5 caras del diente
            toothWrapper.querySelectorAll('.fdi-surface').forEach(s => {
                s.style.fill = (selectedCond === 'sano') ? '#FFFFFF' : condObj.color;
            });

            // Limpiar overlays previos
            if (box) {
                box.querySelectorAll('.tooth-overlay-icon').forEach(el => el.remove());
                if (selectedCond === 'extraccion') {
                    box.insertAdjacentHTML('afterbegin', '<div class="tooth-overlay-icon extraccion"><i class="fas fa-times"></i></div>');
                } else if (selectedCond === 'ausente') {
                    box.insertAdjacentHTML('afterbegin', '<div class="tooth-overlay-icon ausente"><i class="fas fa-ban"></i></div>');
                } else if (selectedCond === 'implante') {
                    box.insertAdjacentHTML('afterbegin', '<div class="tooth-overlay-icon implante"><i class="fas fa-screwdriver"></i></div>');
                }
            }

            showLandingOdontoFeedback(`Diente #${diente}: ${condObj.nombre} (Diente Completo)`);
        } else {
            // Modo superficie específica
            if (surfEl) {
                surfEl.style.fill = (selectedCond === 'sano') ? '#FFFFFF' : condObj.color;
                const surfKey = surfEl.getAttribute('data-surface');
                const surfName = surfaceNames[surfKey] || surfKey;
                showLandingOdontoFeedback(`Diente #${diente} (Cara ${surfName}): ${condObj.nombre}`);
            } else {
                // Si hizo clic en el wrapper fuera de una cara en modo superficie, colorear oclusal
                const oclusal = toothWrapper.querySelector('.surface-o');
                if (oclusal) {
                    oclusal.style.fill = (selectedCond === 'sano') ? '#FFFFFF' : condObj.color;
                    showLandingOdontoFeedback(`Diente #${diente} (Cara Oclusal): ${condObj.nombre}`);
                }
            }
        }

        recalculateLandingKPIs();
    });

    function recalculateLandingKPIs() {
        let evaluadosSet = new Set();
        let caries = 0, obturaciones = 0, coronas = 0;

        document.querySelectorAll('.fdi-surface').forEach(s => {
            const fill = (s.style.fill || '').toUpperCase();
            if (fill && fill !== '#FFFFFF' && fill !== 'RGB(255, 255, 255)') {
                const toothWrapper = s.closest('.tooth-wrapper');
                if (toothWrapper) evaluadosSet.add(toothWrapper.getAttribute('data-diente'));

                if (fill.includes('FF4444') || fill.includes('EF4444') || fill.includes('255, 68, 68') || fill.includes('239, 68, 68')) caries++;
                if (fill.includes('4A90D9') || fill.includes('2563EB') || fill.includes('74, 144, 217') || fill.includes('37, 99, 235')) obturaciones++;
                if (fill.includes('FFD700') || fill.includes('F59E0B') || fill.includes('255, 215, 0') || fill.includes('245, 158, 11')) coronas++;
            }
        });

        // Contar también extracciones / ausentes
        document.querySelectorAll('.tooth-overlay-icon').forEach(icon => {
            const toothWrapper = icon.closest('.tooth-wrapper');
            if (toothWrapper) evaluadosSet.add(toothWrapper.getAttribute('data-diente'));
        });

        const kpiEval = document.getElementById('landing-kpi-evaluados');
        const kpiCaries = document.getElementById('landing-kpi-caries');
        const kpiObt = document.getElementById('landing-kpi-obturaciones');
        const kpiCor = document.getElementById('landing-kpi-coronas');

        if (kpiEval) kpiEval.textContent = evaluadosSet.size;
        if (kpiCaries) kpiCaries.textContent = caries;
        if (kpiObt) kpiObt.textContent = obturaciones;
        if (kpiCor) kpiCor.textContent = coronas;
    }

    function showLandingOdontoFeedback(text) {
        const toast = document.getElementById('landing-odonto-toast-text');
        if (toast) {
            toast.textContent = text;
        }
    }

    // Calcular KPIs iniciales
    setTimeout(recalculateLandingKPIs, 200);
}

/* ==========================================================================
   4. SIMULADOR DE BENEFICIOS PARA TU CLÍNICA
   ========================================================================== */
function initBenefitsSimulator() {
    const sliderCitas = document.getElementById('sliderCitas');
    const sliderTicket = document.getElementById('sliderTicket');

    const valCitas = document.getElementById('valCitas');
    const valTicket = document.getElementById('valTicket');

    const statCitas = document.getElementById('statCitasConfirmadas');
    const statHoras = document.getElementById('statHorasAhorradas');
    const statDinero = document.getElementById('statDineroRecuperado');

    if (!sliderCitas || !sliderTicket) return;

    function recalculate() {
        const citas = parseInt(sliderCitas.value, 10);
        const ticket = parseInt(sliderTicket.value, 10);

        valCitas.textContent = `${citas} citas / mes`;
        valTicket.textContent = `Bs. ${ticket}`;

        // Cálculos claros y realistas:
        // 1. Citas adicionales confirmadas que antes se olvidaban (~15%)
        const citasConfirmadasExtras = Math.round(citas * 0.15);
        
        // 2. Horas ahorradas en llamadas y confirmaciones manuales (~8 min por cita)
        const horasAhorradas = Math.round((citas * 8) / 60);

        // 3. Dinero recuperado por pacientes que asisten gracias a recordatorios
        const dineroRecuperado = citasConfirmadasExtras * ticket;

        if (statCitas) statCitas.textContent = `+${citasConfirmadasExtras} pacientes`;
        if (statHoras) statHoras.textContent = `+${horasAhorradas} horas`;
        if (statDinero) statDinero.textContent = `+Bs. ${dineroRecuperado.toLocaleString()}`;
    }

    sliderCitas.addEventListener('input', recalculate);
    sliderTicket.addEventListener('input', recalculate);

    recalculate();
}

/* ==========================================================================
   5. MODULES TABS CONTROLLER
   ========================================================================== */
function initModuleTabs() {
    const tabButtons = document.querySelectorAll('.module-tab-btn');
    const tabPanes = document.querySelectorAll('.module-tab-pane');
    if (!tabButtons.length) return;

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');

            tabButtons.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.style.display = 'none');

            btn.classList.add('active');
            const targetPane = document.getElementById(targetId);
            if (targetPane) {
                targetPane.style.display = 'grid';
            }
        });
    });
}

/* ==========================================================================
   6. PRICING TOGGLE (MONTHLY VS ANNUAL VS PAGO ÚNICO)
   ========================================================================== */
function initPricingToggle() {
    const segButtons = document.querySelectorAll('.pricing-seg-btn');
    const prices = document.querySelectorAll('.plan-amount');
    const periods = document.querySelectorAll('.plan-period');
    if (!segButtons.length) return;

    segButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            segButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const period = btn.getAttribute('data-period'); // 'monthly' | 'annual' | 'lifetime'

            prices.forEach(priceEl => {
                const monthlyVal = priceEl.getAttribute('data-monthly');
                const annualVal = priceEl.getAttribute('data-annual');
                const lifetimeVal = priceEl.getAttribute('data-lifetime');

                if (period === 'monthly') {
                    priceEl.textContent = monthlyVal;
                } else if (period === 'annual') {
                    priceEl.textContent = annualVal;
                } else if (period === 'lifetime') {
                    priceEl.textContent = lifetimeVal;
                }
            });

            periods.forEach(perEl => {
                if (period === 'monthly') {
                    perEl.textContent = '/mes';
                } else if (period === 'annual') {
                    perEl.textContent = '/mes (facturado anual)';
                } else if (period === 'lifetime') {
                    perEl.textContent = 'pago único (licencia permanente)';
                }
            });
        });
    });
}

/* ==========================================================================
   7. FAQ ACCORDION
   ========================================================================== */
function initFaqAccordion() {
    const faqCards = document.querySelectorAll('.faq-card');
    faqCards.forEach(card => {
        const question = card.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', () => {
                const isOpen = card.classList.contains('open');
                faqCards.forEach(c => c.classList.remove('open'));
                if (!isOpen) {
                    card.classList.add('open');
                }
            });
        }
    });
}

/* ==========================================================================
   8. DEMO REQUEST MODAL & WHATSAPP REDIRECT
   ========================================================================== */
function initModalDemo() {
    const modal = document.getElementById('demoModal');
    const openBtns = document.querySelectorAll('.btn-open-demo-modal');
    const closeBtn = document.getElementById('closeDemoModal');
    const demoForm = document.getElementById('demoLeadForm');

    if (!modal) return;

    openBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.add('active');
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('active');
        });
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });

    if (demoForm) {
        demoForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const clinica = document.getElementById('inputClinica')?.value || 'Mi Clínica Dental';
            const doctor = document.getElementById('inputDoctor')?.value || 'Doctor/a';
            const telefono = document.getElementById('inputTelefono')?.value || '';
            const ciudad = document.getElementById('inputCiudad')?.value || 'Bolivia';
            const plan = document.getElementById('inputPlanInteres')?.value || 'Plan Clínica Pro';

            const mensajeWa = `¡Hola Dra. Tatiana Ruiz! 👋✨ Soy *${doctor}* de la clínica *${clinica}* (${ciudad}). Me gustaría agendar una demostración en vivo de su software y cotizar el *${plan}*. Celular: ${telefono}`;
            const urlWa = `https://wa.me/59176969699?text=${encodeURIComponent(mensajeWa)}`;

            modal.classList.remove('active');
            window.open(urlWa, '_blank');
        });
    }
}
