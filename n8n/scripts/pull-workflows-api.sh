#!/bin/bash
# Pull workflow files from n8n via REST API
# Usage: ./pull-workflows-api.sh [workflow-name]
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

# Configurazione
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
log_debug() { [ "${DEBUG:-}" = "1" ] && echo -e "${BLUE}[DEBUG]${NC} $1" || true; }

# Verifica prerequisiti
check_prerequisites() {
    if [ -z "${N8N_API_KEY}" ]; then
        log_error "N8N_API_KEY non configurata"
        echo ""
        echo "Per generare una API key:"
        echo "1. Accedi a n8n (${N8N_BASE_URL})"
        echo "2. Vai su Settings > API"
        echo "3. Crea una nuova API key"
        echo "4. Aggiungi al file .env: N8N_API_KEY='your-api-key'"
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

    local url="${N8N_BASE_URL}/api/v1${endpoint}"

    log_debug "API: ${method} ${url}"
    curl -s -X "${method}" \
        -H "X-N8N-API-KEY: ${N8N_API_KEY}" \
        -H "Content-Type: application/json" \
        "${url}"
}

# Converte nome workflow in nome file (slug)
workflow_name_to_filename() {
    local name="$1"
    python3 -c "
import re
name = '''$name'''
slug = name.lower()
slug = re.sub(r'[^a-z0-9]+', '-', slug)
slug = slug.strip('-')
print(slug)
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

# Estrai lista workflow usando python
json_list_workflows() {
    python3 -c "
import sys
import json
import re

def slugify(name):
    slug = name.lower()
    slug = re.sub(r'[^a-z0-9]+', '-', slug)
    return slug.strip('-')

try:
    data = json.load(sys.stdin)
    workflows = data.get('data', [])
    seen = set()
    for wf in workflows:
        wf_id = wf.get('id', '')
        name = wf.get('name', '')
        if wf_id and name and wf_id not in seen:
            seen.add(wf_id)
            filename = slugify(name) + '.json'
            print(f'{wf_id}\t{name}\t{filename}')
except Exception as e:
    print(f'ERROR: {e}', file=sys.stderr)
"
}

# Cerca workflow per nome (partial match, case insensitive)
json_find_workflow() {
    local search="$1"
    python3 -c "
import sys
import json

search = '$search'.lower()
try:
    data = json.load(sys.stdin)
    workflows = data.get('data', [])
    for wf in workflows:
        wf_id = wf.get('id', '')
        name = wf.get('name', '')
        if search in name.lower():
            print(f'{wf_id}\t{name}')
            break
except:
    pass
"
}

# Verifica se risposta contiene errore
json_check_error() {
    python3 -c "
import sys
import json
try:
    data = json.load(sys.stdin)
    if 'message' in data and 'id' not in data:
        print(data.get('message', 'Unknown error'))
    elif 'error' in data:
        print(data.get('error', 'Unknown error'))
except:
    pass
"
}

# Formatta JSON per output
json_format() {
    python3 -c "
import sys
import json
try:
    data = json.load(sys.stdin)
    print(json.dumps(data, indent=2, ensure_ascii=False))
except Exception as e:
    # Se non è JSON valido, stampa l'input originale
    sys.stdin.seek(0)
    print(sys.stdin.read())
"
}

# Lista tutti i workflow
list_workflows() {
    log_info "Recupero lista workflow da n8n..."

    local response
    response=$(api_call GET "/workflows")

    echo ""
    echo "Workflow disponibili:"
    echo "====================="

    echo "$response" | json_list_workflows | while IFS=$'\t' read -r id name filename; do
        echo "  [$id] $name -> $filename"
    done
    echo ""
}

# Scarica un singolo workflow per ID
pull_workflow_by_id() {
    local workflow_id="$1"
    local custom_filename="$2"

    log_info "Scaricando workflow ID: ${workflow_id}..."

    local response
    response=$(api_call GET "/workflows/${workflow_id}")

    # Verifica errore
    local error
    error=$(echo "$response" | json_check_error)
    if [ -n "$error" ]; then
        log_error "Errore API: ${error}"
        return 1
    fi

    # Estrai nome workflow
    local workflow_name
    workflow_name=$(echo "$response" | json_get "name")

    if [ -z "$workflow_name" ]; then
        log_error "Impossibile estrarre nome workflow dalla risposta"
        return 1
    fi

    # Determina nome file
    local filename
    if [ -n "$custom_filename" ]; then
        filename="$custom_filename"
    else
        filename="$(workflow_name_to_filename "$workflow_name").json"
    fi

    local filepath="${WORKFLOWS_DIR}/${filename}"

    # Salva il workflow (formattato)
    echo "$response" | json_format > "$filepath"

    log_info "✓ Salvato: ${filename}"
    log_info "  Nome: ${workflow_name}"
    log_info "  Path: ${filepath}"

    return 0
}

# Cerca workflow per nome e scarica
pull_workflow_by_name() {
    local search_name="$1"
    local custom_filename="$2"

    log_info "Cercando workflow: ${search_name}..."

    local response
    response=$(api_call GET "/workflows")

    local found
    found=$(echo "$response" | json_find_workflow "$search_name")

    if [ -z "$found" ]; then
        log_error "Workflow non trovato: ${search_name}"
        log_info "Usa --list per vedere i workflow disponibili"
        return 1
    fi

    local found_id found_name
    found_id=$(echo "$found" | cut -f1)
    found_name=$(echo "$found" | cut -f2)

    log_info "Trovato: ${found_name} (ID: ${found_id})"
    pull_workflow_by_id "$found_id" "$custom_filename"
}

# Scarica tutti i workflow
pull_all_workflows() {
    log_info "Scaricando tutti i workflow..."

    local response
    response=$(api_call GET "/workflows")

    local success=0
    local failed=0

    echo "$response" | json_list_workflows | while IFS=$'\t' read -r id name filename; do
        if pull_workflow_by_id "$id" "$filename"; then
            success=$((success + 1))
        else
            failed=$((failed + 1))
        fi
        echo ""
    done

    log_info "Completato"
}

# Mostra diff tra locale e remoto
diff_workflow() {
    local search_name="$1"

    log_info "Confrontando workflow: ${search_name}..."

    local response
    response=$(api_call GET "/workflows")

    local found
    found=$(echo "$response" | json_find_workflow "$search_name")

    if [ -z "$found" ]; then
        log_error "Workflow non trovato: ${search_name}"
        return 1
    fi

    local found_id found_name
    found_id=$(echo "$found" | cut -f1)
    found_name=$(echo "$found" | cut -f2)

    # Scarica workflow remoto
    local remote_workflow
    remote_workflow=$(api_call GET "/workflows/${found_id}")

    local filename
    filename="$(workflow_name_to_filename "$found_name").json"
    local local_file="${WORKFLOWS_DIR}/${filename}"

    if [ ! -f "$local_file" ]; then
        log_warn "File locale non trovato: ${filename}"
        log_info "Il workflow esiste solo su n8n"
        return 0
    fi

    # Crea file temporaneo per il remoto (formattato)
    local tmp_file
    tmp_file=$(mktemp)
    echo "$remote_workflow" | json_format > "$tmp_file"

    log_info "Differenze tra locale e remoto per: ${found_name}"
    echo ""

    if command -v diff &> /dev/null; then
        diff -u "$local_file" "$tmp_file" | head -100 || true
    else
        log_warn "diff non disponibile"
    fi

    rm -f "$tmp_file"
}

# Main
main() {
    check_prerequisites

    case "${1:-}" in
        --list|-l)
            list_workflows
            ;;
        --all|-a)
            pull_all_workflows
            ;;
        --diff|-d)
            if [ -z "$2" ]; then
                log_error "Specificare nome workflow per diff"
                exit 1
            fi
            diff_workflow "$2"
            ;;
        --id|-i)
            if [ -z "$2" ]; then
                log_error "Specificare ID workflow"
                exit 1
            fi
            pull_workflow_by_id "$2" "${3:-}"
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS] [workflow-name] [output-filename]"
            echo ""
            echo "Pull n8n workflows via REST API"
            echo ""
            echo "Environment:"
            echo "  N8N_API_KEY     API key (obbligatoria)"
            echo "  N8N_BASE_URL    URL n8n (default: https://n8n.informabm.com)"
            echo "  DEBUG=1         Mostra debug output"
            echo ""
            echo "Options:"
            echo "  --list, -l              Lista workflow disponibili"
            echo "  --all, -a               Scarica tutti i workflow"
            echo "  --diff, -d <name>       Mostra diff locale vs remoto"
            echo "  --id, -i <id> [file]    Scarica per ID con nome file opzionale"
            echo "  --help, -h              Mostra questo messaggio"
            echo ""
            echo "Examples:"
            echo "  $0 --list                           # Lista workflow"
            echo "  $0 'Slack Router'                   # Scarica per nome (partial match)"
            echo "  $0 'Slack Router' slack-router.json # Scarica con nome file custom"
            echo "  $0 --id abc123                      # Scarica per ID"
            echo "  $0 --diff 'Slack Router'            # Mostra differenze"
            echo "  $0 --all                            # Scarica tutti"
            ;;
        "")
            echo "Specificare un workflow da scaricare o usare --help"
            echo ""
            list_workflows
            ;;
        *)
            pull_workflow_by_name "$1" "${2:-}"
            ;;
    esac
}

main "$@"
