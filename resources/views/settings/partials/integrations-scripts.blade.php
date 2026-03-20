{{-- Alpine component for API key fields on integrations page --}}
<script>
function integrationField(serviceName, currentKey, saveUrl, testUrl) {
    return {
        serviceName: serviceName,
        storedKey: currentKey,
        newKey: '',
        editing: false,
        saving: false,
        testing: false,
        resultMessage: '',
        resultSuccess: false,
        async saveKey() {
            this.saving = true;
            this.resultMessage = '';
            try {
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ settings: { [this.serviceName + '_api_key']: this.newKey } })
                });
                const data = await res.json();
                this.resultSuccess = data.success !== false;
                this.resultMessage = data.message || 'Saved.';
                if (this.resultSuccess) {
                    this.storedKey = this.newKey;
                    this.newKey = '';
                    this.editing = false;
                }
            } catch (e) {
                this.resultSuccess = false;
                this.resultMessage = 'Error: ' + e.message;
            }
            this.saving = false;
        },
        async testKey() {
            this.testing = true;
            this.resultMessage = '';
            try {
                const res = await fetch(testUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ service: this.serviceName })
                });
                const data = await res.json();
                this.resultSuccess = data.success;
                this.resultMessage = data.message;
            } catch (e) {
                this.resultSuccess = false;
                this.resultMessage = 'Error: ' + e.message;
            }
            this.testing = false;
        }
    };
}
</script>
