import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["form", "progressBar", "progressText", "messages"]
    static values = { 
        passationId: Number,
        csrfToken: String
    }

    connect() {
        console.log("Test passation controller connected")
        this.setupAutoSave()
        this.setupBeforeUnload()
    }

    disconnect() {
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout)
        }
        window.removeEventListener('beforeunload', this.beforeUnloadHandler)
    }

    setupAutoSave() {
        // Auto-save après 2 secondes d'inactivité
        this.formTarget.addEventListener('change', () => {
            this.scheduleAutoSave()
        })
    }

    setupBeforeUnload() {
        this.beforeUnloadHandler = (e) => {
            this.saveCurrentResponses()
        }
        window.addEventListener('beforeunload', this.beforeUnloadHandler)
    }

    scheduleAutoSave() {
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout)
        }
        
        this.autoSaveTimeout = setTimeout(() => {
            this.saveCurrentResponses()
        }, 2000)
    }

    saveResponse(event) {
        const itemId = event.target.dataset.itemId
        const response = parseInt(event.target.value)
        
        this.saveIndividualResponse(itemId, response)
    }

    async saveIndividualResponse(itemId, response) {
        try {
            const url = `/patient/passation/${this.passationIdValue}/save-response`
            const response_data = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfTokenValue
                },
                body: JSON.stringify({
                    item_id: itemId,
                    reponse: response
                })
            })

            const data = await response_data.json()
            
            if (data.success) {
                this.updateProgress(data.progression)
                this.showMessage('Réponse sauvegardée', 'success', 2000)
            } else {
                this.showMessage('Erreur lors de la sauvegarde: ' + data.error, 'error')
            }
        } catch (error) {
            console.error('Erreur:', error)
            this.showMessage('Erreur de connexion', 'error')
        }
    }

    async saveCurrentResponses() {
        const formData = new FormData(this.formTarget)
        const responses = {}
        
        for (let [key, value] of formData.entries()) {
            if (key.startsWith('item_')) {
                const itemId = key.replace('item_', '')
                responses[itemId] = parseInt(value)
            }
        }
        
        if (Object.keys(responses).length === 0) {
            return
        }

        try {
            const url = `/patient/passation/${this.passationIdValue}/save-responses`
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfTokenValue
                },
                body: JSON.stringify({ reponses: responses })
            })

            const data = await response.json()
            
            if (data.success) {
                this.updateProgress(data.progression)
                this.showMessage(`${data.message}`, 'success', 2000)
            } else {
                this.showMessage('Erreur lors de la sauvegarde: ' + data.error, 'error')
            }
        } catch (error) {
            console.error('Erreur:', error)
        }
    }

    suspend(event) {
        event.preventDefault()
        
        this.showConfirmModal(
            'Suspendre le test',
            'Voulez-vous suspendre le test ? Vous pourrez le reprendre plus tard.',
            () => {
                this.performSuspend()
            }
        )
    }

    async performSuspend() {
        try {
            // Sauvegarder d'abord les réponses actuelles
            await this.saveCurrentResponses()
            
            // Puis suspendre
            const url = `/patient/passation/${this.passationIdValue}/suspend`
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfTokenValue
                }
            })

            if (response.ok) {
                window.location.href = '/patient/dashboard'
            } else {
                this.showMessage('Erreur lors de la suspension', 'error')
            }
        } catch (error) {
            console.error('Erreur:', error)
            this.showMessage('Erreur de connexion', 'error')
        }
    }

    preview(event) {
        event.preventDefault()
        window.open(`/patient/passation/${this.passationIdValue}/preview`, '_blank')
    }

    abandon(event) {
        event.preventDefault()
        
        this.showConfirmModal(
            'Abandonner le test',
            'Êtes-vous sûr de vouloir abandonner ce test ? Cette action est irréversible.',
            () => {
                this.performAbandon()
            },
            'danger'
        )
    }

    async performAbandon() {
        try {
            const url = `/patient/passation/${this.passationIdValue}/abandon`
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfTokenValue
                }
            })

            if (response.ok) {
                window.location.href = '/patient/tests'
            } else {
                this.showMessage('Erreur lors de l\'abandon', 'error')
            }
        } catch (error) {
            console.error('Erreur:', error)
            this.showMessage('Erreur de connexion', 'error')
        }
    }

    finish(event) {
        event.preventDefault()
        
        // Vérifier si toutes les questions ont une réponse
        const unansweredItems = this.getUnansweredItems()
        
        if (unansweredItems.length > 0) {
            this.showMessage(
                `Il reste ${unansweredItems.length} question(s) sans réponse. Veuillez compléter le test avant de le finaliser.`,
                'warning'
            )
            // Faire défiler vers la première question non répondue
            unansweredItems[0].scrollIntoView({ behavior: 'smooth', block: 'center' })
            return
        }
        
        this.showConfirmModal(
            'Terminer le test',
            'Voulez-vous terminer et soumettre ce test ? Cette action est irréversible.',
            () => {
                this.performFinish()
            }
        )
    }

    async performFinish() {
        try {
            // Sauvegarder les dernières réponses et terminer
            const formData = new FormData(this.formTarget)
            const responses = {}
            
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('item_')) {
                    const itemId = key.replace('item_', '')
                    responses[itemId] = parseInt(value)
                }
            }

            const url = `/patient/passation/${this.passationIdValue}/finish`
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfTokenValue
                },
                body: JSON.stringify({ reponses: responses })
            })

            const data = await response.json()
            
            if (data.success) {
                this.showMessage('Test terminé avec succès !', 'success')
                setTimeout(() => {
                    window.location.href = data.redirect
                }, 2000)
            } else {
                this.showMessage('Erreur lors de la finalisation: ' + data.error, 'error')
            }
        } catch (error) {
            console.error('Erreur:', error)
            this.showMessage('Erreur de connexion', 'error')
        }
    }

    getUnansweredItems() {
        const allRadioGroups = {}
        const radios = this.formTarget.querySelectorAll('input[type="radio"]')
        
        // Grouper les radios par nom
        radios.forEach(radio => {
            if (!allRadioGroups[radio.name]) {
                allRadioGroups[radio.name] = []
            }
            allRadioGroups[radio.name].push(radio)
        })
        
        // Trouver les groupes sans réponse
        const unanswered = []
        Object.keys(allRadioGroups).forEach(groupName => {
            const group = allRadioGroups[groupName]
            const hasChecked = group.some(radio => radio.checked)
            if (!hasChecked) {
                unanswered.push(group[0]) // Retourner le premier radio du groupe
            }
        })
        
        return unanswered
    }

    updateProgress(progression) {
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = progression + '%'
        }
        if (this.hasProgressTextTarget) {
            this.progressTextTarget.textContent = progression + '%'
        }
    }

    showMessage(message, type = 'info', duration = 5000) {
        if (!this.hasMessagesTarget) return
        
        const alertClass = {
            'success': 'bg-green-50 text-green-800 border-green-200',
            'error': 'bg-red-50 text-red-800 border-red-200',
            'warning': 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'info': 'bg-blue-50 text-blue-800 border-blue-200'
        }[type] || 'bg-blue-50 text-blue-800 border-blue-200'
        
        const iconClass = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        }[type] || 'fa-info-circle'
        
        const messageDiv = document.createElement('div')
        messageDiv.className = `mb-4 p-3 rounded-lg border ${alertClass} transition-opacity duration-300`
        messageDiv.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="ml-3">
                    ${message}
                </div>
            </div>
        `
        
        this.messagesTarget.appendChild(messageDiv)
        this.messagesTarget.classList.remove('hidden')
        
        // Supprimer le message après la durée spécifiée
        setTimeout(() => {
            messageDiv.style.opacity = '0'
            setTimeout(() => {
                messageDiv.remove()
                if (this.messagesTarget.children.length === 0) {
                    this.messagesTarget.classList.add('hidden')
                }
            }, 300)
        }, duration)
    }

    showConfirmModal(title, message, onConfirm, type = 'default') {
        const modal = document.getElementById('confirm-modal')
        const modalTitle = document.getElementById('modal-title')
        const modalMessage = document.getElementById('modal-message')
        const modalCancel = document.getElementById('modal-cancel')
        const modalConfirm = document.getElementById('modal-confirm')
        
        modalTitle.textContent = title
        modalMessage.textContent = message
        
        // Changer la couleur du bouton selon le type
        if (type === 'danger') {
            modalConfirm.className = modalConfirm.className.replace('bg-blue-600 hover:bg-blue-700', 'bg-red-600 hover:bg-red-700')
        } else {
            modalConfirm.className = modalConfirm.className.replace('bg-red-600 hover:bg-red-700', 'bg-blue-600 hover:bg-blue-700')
        }
        
        // Gestionnaires d'événements
        const handleCancel = () => {
            modal.classList.add('hidden')
            modalCancel.removeEventListener('click', handleCancel)
            modalConfirm.removeEventListener('click', handleConfirm)
        }
        
        const handleConfirm = () => {
            modal.classList.add('hidden')
            onConfirm()
            modalCancel.removeEventListener('click', handleCancel)
            modalConfirm.removeEventListener('click', handleConfirm)
        }
        
        modalCancel.addEventListener('click', handleCancel)
        modalConfirm.addEventListener('click', handleConfirm)
        
        // Fermer avec Escape
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                handleCancel()
                document.removeEventListener('keydown', handleEscape)
            }
        }
        document.addEventListener('keydown', handleEscape)
        
        modal.classList.remove('hidden')
    }
}
