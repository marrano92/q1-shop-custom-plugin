(function($) {
	'use strict';

	var Q1ContentIdeas = {

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('#q1-ideas-analyze-btn').on('click', this.analyzeSite.bind(this));
			$('#q1-ideas-save-context-btn').on('click', this.saveContext.bind(this));
			$('#q1-ideas-generate-btn').on('click', this.generateIdeas.bind(this));

			// History accordion toggles.
			$(document).on('click', '.q1-ideas-history-toggle', function() {
				var index = $(this).data('index');
				var $detail = $('#q1-ideas-detail-' + index);
				var $icon = $(this).find('.dashicons');

				$detail.toggle();
				$icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
			});
		},

		// --- Fase 1 ---

		analyzeSite: function() {
			var self = this;
			var $btn = $('#q1-ideas-analyze-btn');
			var $spinner = $('#q1-ideas-analyze-spinner');
			var $error = $('#q1-ideas-analyze-error');

			$btn.prop('disabled', true).text(q1SeoIdeas.strings.analyzing);
			$spinner.addClass('is-active');
			$error.hide();

			$.ajax({
				url: q1SeoIdeas.ajaxUrl,
				type: 'POST',
				data: {
					action: 'q1_seo_analyze_site',
					nonce: q1SeoIdeas.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					$btn.prop('disabled', false).text(q1SeoIdeas.strings.analyze);

					if (response.success) {
						$('#q1-ideas-context-text').val(response.data.text);
						$('#q1-ideas-context-box').show();
					} else {
						$error.show().find('p').text(response.data.message || 'Errore.');
					}
				},
				error: function() {
					$spinner.removeClass('is-active');
					$btn.prop('disabled', false).text(q1SeoIdeas.strings.analyze);
					$error.show().find('p').text(q1SeoIdeas.strings.connectionError);
				}
			});
		},

		saveContext: function() {
			var self = this;
			var $btn = $('#q1-ideas-save-context-btn');
			var $status = $('#q1-ideas-save-status');
			var text = $('#q1-ideas-context-text').val().trim();

			if (!text) {
				return;
			}

			$btn.prop('disabled', true).text(q1SeoIdeas.strings.saving);

			$.ajax({
				url: q1SeoIdeas.ajaxUrl,
				type: 'POST',
				data: {
					action: 'q1_seo_save_site_context',
					nonce: q1SeoIdeas.nonce,
					context_text: text
				},
				success: function(response) {
					$btn.prop('disabled', false).text(q1SeoIdeas.strings.saveContext);

					if (response.success) {
						$status.text(q1SeoIdeas.strings.lastSaved + ' ' + response.data.updated_at);
						$('#q1-ideas-generate-btn').prop('disabled', false);
					} else {
						$status.text(response.data.message || 'Errore.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text(q1SeoIdeas.strings.saveContext);
					$status.text(q1SeoIdeas.strings.connectionError);
				}
			});
		},

		// --- Fase 2 ---

		generateIdeas: function() {
			var self = this;
			var $btn = $('#q1-ideas-generate-btn');
			var $loading = $('#q1-ideas-loading');
			var $error = $('#q1-ideas-error');
			var $data = $('#q1-ideas-data');

			$btn.prop('disabled', true).text(q1SeoIdeas.strings.generating);
			$loading.show();
			$error.hide();
			$data.empty();

			$.ajax({
				url: q1SeoIdeas.ajaxUrl,
				type: 'POST',
				data: {
					action: 'q1_seo_generate_ideas',
					nonce: q1SeoIdeas.nonce
				},
				timeout: 90000,
				success: function(response) {
					$loading.hide();
					$btn.prop('disabled', false).text(q1SeoIdeas.strings.generate);

					if (response.success) {
						self.renderIdeas(response.data);
					} else {
						self.showError(response.data.message || 'Errore.');
					}
				},
				error: function(xhr, status) {
					$loading.hide();
					$btn.prop('disabled', false).text(q1SeoIdeas.strings.generate);

					if (status === 'timeout') {
						self.showError('La richiesta ha impiegato troppo tempo. Riprova.');
					} else {
						self.showError(q1SeoIdeas.strings.connectionError);
					}
				}
			});
		},

		renderIdeas: function(data) {
			var $container = $('#q1-ideas-data');
			$container.empty();

			if (!data.ideas || data.ideas.length === 0) {
				$container.html('<p>' + q1SeoIdeas.strings.noIdeas + '</p>');
				return;
			}

			// Strategy notes.
			if (data.strategy_notes) {
				var notesHtml = '<div class="q1-ideas-strategy-notes">';
				notesHtml += '<h3>' + this.escapeHtml(q1SeoIdeas.strings.strategyNotes) + '</h3>';
				notesHtml += '<p>' + this.escapeHtml(data.strategy_notes) + '</p>';
				notesHtml += '</div>';
				$container.append(notesHtml);
			}

			// Meta info.
			if (data.meta) {
				var metaHtml = '<div class="q1-seo-meta">';
				metaHtml += '<span>' + data.meta.total_ideas + ' idee generate</span>';
				if (data.meta.competitor_articles_analyzed) {
					metaHtml += '<span>' + data.meta.competitor_articles_analyzed + ' articoli competitor analizzati</span>';
				}
				metaHtml += '</div>';
				$container.append(metaHtml);
			}

			// Export CSV button.
			var actionsHtml = '<div class="q1-seo-actions">';
			actionsHtml += '<button type="button" id="q1-ideas-export-csv" class="button">';
			actionsHtml += this.escapeHtml(q1SeoIdeas.strings.exportCsv);
			actionsHtml += '</button>';
			actionsHtml += '</div>';
			$container.append(actionsHtml);

			// Ideas grid.
			var gridHtml = '<div class="q1-ideas-grid">';
			for (var i = 0; i < data.ideas.length; i++) {
				gridHtml += this.renderIdeaCard(data.ideas[i], i);
			}
			gridHtml += '</div>';
			$container.append(gridHtml);

			this.currentIdeas = data.ideas;

			// Bind actions.
			var self = this;
			$('#q1-ideas-export-csv').on('click', this.exportCSV.bind(this));
			$(document).off('click.q1ideas').on('click.q1ideas', '.q1-ideas-create-draft-btn', function() {
				var idx = $(this).data('index');
				self.createDraft(self.currentIdeas[idx]);
			});
		},

		renderIdeaCard: function(idea, index) {
			var html = '<div class="q1-idea-card">';

			if (idea.priority) {
				html += '<span class="q1-idea-badge q1-idea-badge--priority-' + parseInt(idea.priority, 10) + '">';
				html += '#' + parseInt(idea.priority, 10);
				html += '</span>';
			}

			html += '<div class="q1-idea-title">' + this.escapeHtml(idea.title) + '</div>';

			if (idea.subtitle) {
				html += '<div class="q1-idea-subtitle">' + this.escapeHtml(idea.subtitle) + '</div>';
			}

			if (idea.description) {
				html += '<div class="q1-idea-description">' + this.escapeHtml(idea.description) + '</div>';
			}

			html += '<div class="q1-idea-meta">';

			if (idea.target_keywords && idea.target_keywords.length > 0) {
				for (var j = 0; j < idea.target_keywords.length; j++) {
					html += '<span class="q1-idea-keyword-badge">' + this.escapeHtml(idea.target_keywords[j]) + '</span>';
				}
			}

			if (idea.estimated_volume) {
				html += '<span class="q1-idea-volume">' + this.formatNumber(idea.estimated_volume) + ' vol.</span>';
			}

			if (idea.difficulty) {
				html += '<span class="q1-idea-badge q1-idea-badge--' + this.escapeHtml(idea.difficulty) + '">' + this.escapeHtml(idea.difficulty) + '</span>';
			}

			if (idea.content_type) {
				html += '<span class="q1-idea-badge q1-idea-badge--type">' + this.escapeHtml(idea.content_type) + '</span>';
			}

			html += '</div>';

			html += '<div class="q1-idea-actions">';
			html += '<button type="button" class="button button-small q1-ideas-create-draft-btn" data-index="' + index + '">';
			html += this.escapeHtml(q1SeoIdeas.strings.createDraft);
			html += '</button>';
			html += '</div>';

			html += '</div>';
			return html;
		},

		// --- Azioni ---

		createDraft: function(idea) {
			if (!confirm(q1SeoIdeas.strings.confirmDraft.replace('%s', idea.title))) {
				return;
			}

			$.ajax({
				url: q1SeoIdeas.ajaxUrl,
				type: 'POST',
				data: {
					action: 'q1_seo_create_idea_draft',
					nonce: q1SeoIdeas.nonce,
					title: idea.title,
					subtitle: idea.subtitle || '',
					description: idea.description || '',
					primary_keyword: idea.primary_keyword || '',
					target_keywords: idea.target_keywords ? idea.target_keywords.join(',') : ''
				},
				success: function(response) {
					if (response.success && response.data.edit_url) {
						window.location.href = response.data.edit_url;
					} else {
						alert(response.data.message || 'Errore nella creazione della bozza.');
					}
				},
				error: function() {
					alert(q1SeoIdeas.strings.connectionError);
				}
			});
		},

		exportCSV: function() {
			var ideas = this.currentIdeas;
			if (!ideas || ideas.length === 0) return;

			var csv = 'Titolo,Sottotitolo,Descrizione,Keyword Principale,Keywords Target,Volume Stimato,Difficoltà,Tipo,Priorità\n';

			for (var i = 0; i < ideas.length; i++) {
				var idea = ideas[i];
				csv += '"' + (idea.title || '').replace(/"/g, '""') + '",';
				csv += '"' + (idea.subtitle || '').replace(/"/g, '""') + '",';
				csv += '"' + (idea.description || '').replace(/"/g, '""') + '",';
				csv += '"' + (idea.primary_keyword || '').replace(/"/g, '""') + '",';
				csv += '"' + (idea.target_keywords ? idea.target_keywords.join(', ') : '').replace(/"/g, '""') + '",';
				csv += (idea.estimated_volume || 0) + ',';
				csv += '"' + (idea.difficulty || '').replace(/"/g, '""') + '",';
				csv += '"' + (idea.content_type || '').replace(/"/g, '""') + '",';
				csv += (idea.priority || '') + '\n';
			}

			var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = 'content-ideas-' + new Date().toISOString().slice(0, 10) + '.csv';
			link.click();
			URL.revokeObjectURL(url);
		},

		// --- Utility ---

		showError: function(message) {
			$('#q1-ideas-error').show().find('p').text(message);
		},

		formatNumber: function(num) {
			if (num >= 1000) {
				return (num / 1000).toFixed(1).replace('.0', '') + 'K';
			}
			return num.toString();
		},

		escapeHtml: function(text) {
			if (!text) return '';
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		}
	};

	$(document).ready(function() {
		Q1ContentIdeas.init();
	});

})(jQuery);
