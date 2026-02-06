#!/bin/bash
# Sync workflow files to n8n via REST API
# Usage: ./sync-workflows-api.sh [workflow-file]
#
# Richiede:
# - N8N_API_KEY: API key generata in n8n (Settings > API)
# - N8N_BASE_URL: URL base di n8n (default: https://n8n.informabm.com)

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
N8N_DIR="${SCRIPT_DIR}/.."
WORKFLOWS_DIR="${N8N_DIR}/workflows"

# Carica .env se esiste
if [ -f "${N8N_DIR}/.env" ]; then
    set -a
    source "${N8N_DIR}/.env"
    set +a
fi

# Configurazione (può essere sovrascritta da variabili d'ambiente)
N8N_BASE_URL="${N8N_BASE_URL:-https://n8n.informabm.com}"
N8N_API_KEY="${N8N_API_KEY:-}"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_debug() { [ "${DEBUG:-}" = "1" ] && echo -e "${BLUE}[DEBUG]${NC} $1"; }

# Verifica prerequisiti
check_prerequisites() {
    if [ -z "${N8N_API_KEY}" ]; then
        log_error "N8N_API_KEY non configurata"
        echo ""
        echo "Per generare una API key:"
        echo "1. Accedi a n8n (${N8N_BASE_URL})"
        echo "2. Vai su Settings > API"
        echo "3. Crea una nuova API key"
        echo "4. Esporta: export N8N_API_KEY='your-api-key'"
        exit 1
    fi

    if ! command -v curl &> /dev/null; then
        log_error "curl non installato"
        exit 1
    fi

    if ! command -v python3 &> /dev/null; then
        log_error "python3 non installato (richiesto per parsing JSON)"
        exit 1
    fi
}

# API call helper
api_call() {
    local method="$1"
    local endpoint="$2"
    local data="${3:-}"

    local url="${N8N_BASE_URL}/api/v1${endpoint}"
    local curl_args=(
        -s
        -X "${method}"
        -H "X-N8N-API-KEY: ${N8N_API_KEY}"
        -H "Content-Type: application/json"
    )

    if [ -n "$data" ]; then
        curl_args+=(-d "$data")
    fi

    log_debug "API: ${method} ${url}"
    curl "${curl_args[@]}" "${url}"
}

# Ottieni lista workflow esistenti (usando python)
get_existing_workflows() {
    api_call GET "/workflows" | python3 -c "
import sys
import json
try:
    data = json.load(sys.stdin)
    for wf in data.get('data', []):
        wf_id = wf.get('id', '')
        name = wf.get('name', '')
        if wf_id and name:
            print(f'{wf_id}:{name}')
except:
    pass
"
}

# Trova workflow ID per nome
find_workflow_id() {
    local name="$1"
    local workflows
    workflows=$(get_existing_workflows)

    echo "$workflows" | while IFS=: read -r id wf_name; do
        if [ "$wf_name" = "$name" ]; then
            echo "$id"
            return
        fi
    done
}

# Pulisce il JSON workflow per l'API (rimuove proprietà non accettate)
clean_workflow_json() {
    local file="$1"
    python3 -c "
import sys
import json

# Proprietà accettate dall'API n8n per update/create
# Nota: 'active' è read-only, si usa PATCH per attivare/disattivare
ALLOWED_PROPS = {'name', 'nodes', 'connections', 'settings'}

# Proprietà accettate in settings dall'API n8n
ALLOWED_SETTINGS = {
    'executionOrder', 'saveDataErrorExecution', 'saveDataSuccessExecution',
    'executionTimeout', 'timezone', 'callerPolicy', 'errorWorkflow'
}

with open('$file') as f:
    wf = json.load(f)

# Filtra solo le proprietà accettate
cleaned = {k: v for k, v in wf.items() if k in ALLOWED_PROPS}

# Filtra le sotto-proprietà di settings
if 'settings' in cleaned and isinstance(cleaned['settings'], dict):
    cleaned['settings'] = {k: v for k, v in cleaned['settings'].items() if k in ALLOWED_SETTINGS}

print(json.dumps(cleaned, ensure_ascii=False))
"
}

# Estrai campo JSON usando python
json_get() {
    local field="$1"
    python3 -c "
import sys
import json
try:
    data = json.load(sys.stdin)
    value = data.get('$field', '')
    print(value if value else '')
except:
    print('')
"
}

# Verifica se risposta contiene ID (successo)
json_has_id() {
    python3 -c "
import sys
import json
try:
    data = json.load(sys.stdin)
    if 'id' in data:
        print('true')
    else:
        print('false')
except:
    print('false')
"
}

# Estrai messaggio errore
json_get_error() {
    python3 -c "
import sys
import json
try:
    data = json.load(sys.stdin)
    msg = data.get('message', data.get('error', str(data)))
    print(msg)
except Exception as e:
    print(str(e))
"
}

# Importa/aggiorna workflow
import_workflow() {
    local file="$1"
    local filename=$(basename "$file")

    log_info "Processando ${filename}..."

    # Leggi e pulisci il file JSON
    local workflow_json
    workflow_json=$(clean_workflow_json "$file")

    # Estrai nome workflow
    local workflow_name
    workflow_name=$(echo "$workflow_json" | json_get "name")

    if [ -z "$workflow_name" ]; then
        log_error "Impossibile leggere nome workflow da ${filename}"
        return 1
    fi

    log_info "Workflow: ${workflow_name}"

    # Cerca se esiste già
    local existing_id
    existing_id=$(find_workflow_id "$workflow_name")

    if [ -n "$existing_id" ]; then
        # Aggiorna workflow esistente
        log_info "Aggiornando workflow esistente (ID: ${existing_id})..."
        local response
        response=$(api_call PUT "/workflows/${existing_id}" "$workflow_json")

        if [ "$(echo "$response" | json_has_id)" = "true" ]; then
            log_info "✓ Workflow aggiornato"
            return 0
        else
            log_error "✗ Errore aggiornamento: $(echo "$response" | json_get_error)"
            return 1
        fi
    else
        # Crea nuovo workflow
        log_info "Creando nuovo workflow..."
        local response
        response=$(api_call POST "/workflows" "$workflow_json")

        if [ "$(echo "$response" | json_has_id)" = "true" ]; then
            local new_id
            new_id=$(echo "$response" | json_get "id")
            log_info "✓ Workflow creato (ID: ${new_id})"
            return 0
        else
            log_error "✗ Errore creazione: $(echo "$response" | json_get_error)"
            return 1
        fi
    fi
}

# Attiva workflow
activate_workflow() {
    local workflow_id="$1"
    log_info "Attivando workflow ${workflow_id}..."
    api_call PATCH "/workflows/${workflow_id}" '{"active": true}' > /dev/null
}

# Lista workflow
list_workflows() {
    log_info "Workflow in n8n:"
    echo ""
    local workflows
    workflows=$(api_call GET "/workflows")

    echo "$workflows" | python3 -c "
import sys
import json
try:
    data = json.load(sys.stdin)
    seen = set()
    for wf in data.get('data', []):
        wf_id = wf.get('id', '')
        if wf_id in seen:
            continue
        seen.add(wf_id)
        name = wf.get('name', '')
        active = 'ATTIVO' if wf.get('active', False) else 'inattivo'
        print(f'  [{wf_id}] {name} - {active}')
except Exception as e:
    print(f'Errore: {e}')
"
}

# Main
main() {
    check_prerequisites

    local success=0
    local failed=0

    if [ -n "$1" ]; then
        # Importa file specifico
        local file="$1"
        if [ ! -f "$file" ]; then
            file="${WORKFLOWS_DIR}/$1"
        fi

        if [ -f "$file" ]; then
            if import_workflow "$file"; then
                success=$((success + 1))
            else
                failed=$((failed + 1))
            fi
        else
            log_error "File non trovato: $1"
            exit 1
        fi
    else
        # Importa tutti i workflow JSON
        for file in "${WORKFLOWS_DIR}"/*.json; do
            if [ -f "$file" ]; then
                if import_workflow "$file"; then
                    success=$((success + 1))
                else
                    failed=$((failed + 1))
                fi
            fi
        done
    fi

    echo ""
    log_info "Completato: ${success} successi, ${failed} fallimenti"

    [ $failed -gt 0 ] && exit 1 || exit 0
}

# Gestione argomenti
case "${1:-}" in
    --list|-l)
        check_prerequisites
        list_workflows
        ;;
    --help|-h)
        echo "Usage: $0 [OPTIONS] [workflow-file]"
        echo ""
        echo "Sync n8n workflows via REST API"
        echo ""
        echo "Environment:"
        echo "  N8N_API_KEY     API key (obbligatoria)"
        echo "  N8N_BASE_URL    URL n8n (default: https://n8n.informabm.com)"
        echo "  DEBUG=1         Mostra debug output"
        echo ""
        echo "Options:"
        echo "  --list, -l    Lista workflow esistenti"
        echo "  --help, -h    Mostra questo messaggio"
        echo ""
        echo "Examples:"
        echo "  export N8N_API_KEY='your-key'"
        echo "  $0                              # Importa tutti"
        echo "  $0 jira-backup-issues.json      # Importa specifico"
        echo "  $0 --list                       # Lista workflow"
        ;;
    *)
        main "$@"
        ;;
esac
