# Makefile per q1-shop-custom-plugin
# Gestione workflow n8n

.PHONY: help workflows-push workflows-push-all workflows-list workflows-pull workflows-pull-all

# Variabili
N8N_SCRIPTS := n8n/scripts
WORKFLOWS_DIR := n8n/workflows

help:
	@echo "Comandi disponibili:"
	@echo "  make workflows-push FILE=nome.json   - Push singolo workflow"
	@echo "  make workflows-push-all              - Push tutti i workflow"
	@echo "  make workflows-list                  - Lista workflow su n8n"
	@echo "  make workflows-pull FILE='nome'      - Pull singolo workflow (cerca per nome)"
	@echo "  make workflows-pull-all              - Pull tutti i workflow"
	@echo ""
	@echo "Prerequisiti:"
	@echo "  export N8N_API_KEY='your-api-key'"

# Push singolo workflow
workflows-push:
ifndef FILE
	$(error FILE non specificato. Uso: make workflows-push FILE=seo-keyword-research.json)
endif
	@$(N8N_SCRIPTS)/sync-workflows-api.sh $(FILE)

# Push tutti i workflow
workflows-push-all:
	@$(N8N_SCRIPTS)/sync-workflows-api.sh

# Lista workflow esistenti su n8n
workflows-list:
	@$(N8N_SCRIPTS)/sync-workflows-api.sh --list

# Pull singolo workflow da n8n
workflows-pull:
ifndef FILE
	@$(N8N_SCRIPTS)/pull-workflows-api.sh --list
	@echo ""
	@echo "Uso: make workflows-pull FILE='nome-workflow'"
else
	@$(N8N_SCRIPTS)/pull-workflows-api.sh "$(FILE)"
endif

# Pull tutti i workflow da n8n
workflows-pull-all:
	@$(N8N_SCRIPTS)/pull-workflows-api.sh --all
