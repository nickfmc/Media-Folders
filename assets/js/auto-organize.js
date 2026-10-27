/**
 * Apex Folders - Auto-organize with AI
 *
 * One click asks the AI provider connected to WordPress for a plan, shows it
 * for review (with a box to ask for changes), applies it, and can undo it.
 */
(function($) {
    'use strict';

    var config = window.apexfoOrganize || {};
    var strings = config.strings || {};
    // Localized scalars arrive as strings
    var batchSize = parseInt(config.batchSize, 10) || 20;
    var apiFetch = window.wp && wp.apiFetch;

    if (!apiFetch) {
        return;
    }

    /**
     * Fill %1$s / %2$s placeholders
     */
    function format(text) {
        var values = Array.prototype.slice.call(arguments, 1);
        return String(text || '').replace(/%(\d+)\$s/g, function(match, position) {
            var value = values[parseInt(position, 10) - 1];
            return typeof value === 'undefined' ? match : value;
        });
    }

    function formatNumber(n) {
        return Number(n || 0).toLocaleString();
    }

    function formatMoney(n) {
        n = Number(n || 0);
        if (n > 0 && n < 0.01) {
            return '$' + n.toFixed(3);
        }
        return '$' + n.toFixed(2);
    }

    function request(path, options) {
        return apiFetch($.extend({ path: config.restBase + '/' + path }, options || {}));
    }

    function errorMessage(error) {
        return (error && error.message) ? error.message : strings.error;
    }

    /**
     * The dialog and its state
     */
    var Organizer = {
        $dialog: null,
        scope: 'unassigned',
        estimate: null,
        ids: [],
        instructions: '',
        cancelled: false,
        proposals: [],
        usage: { input: 0, output: 0, total: 0 },
        model: '',
        newFolders: [],
        skipped: {},

        open: function() {
            var self = this;

            this.$dialog = $('<div class="apexfo-organize-dialog"></div>').dialog({
                title: strings.title,
                dialogClass: 'wp-dialog apexfo-dialog',
                modal: true,
                resizable: false,
                draggable: false,
                width: 560,
                closeText: strings.close,
                close: function() {
                    self.cancelled = true;
                    $(this).dialog('destroy').remove();
                    self.$dialog = null;
                }
            });

            this.showEstimate();
        },

        setButtons: function(buttons) {
            this.$dialog.dialog('option', 'buttons', buttons);
        },

        // ------------------------------------------------------------ step 1

        showEstimate: function() {
            var self = this;
            var $body = this.$dialog.empty();

            $body.append($('<p class="apexfo-dialog-intro"></p>').text(strings.intro));

            var $scope = $('<fieldset class="apexfo-scope"></fieldset>');
            $.each([['unassigned', strings.scopeUnassigned], ['all', strings.scopeAll]], function(i, option) {
                var $label = $('<label></label>');
                var $input = $('<input type="radio" name="apexfo-scope">').val(option[0]).prop('checked', option[0] === self.scope);
                $label.append($input, document.createTextNode(' ' + option[1]));
                $scope.append($label);
            });
            $body.append($scope);

            var $summary = $('<div class="apexfo-estimate"><span class="spinner is-active"></span></div>');
            $body.append($summary);

            $scope.on('change', 'input', function() {
                self.scope = $(this).val();
                self.loadEstimate($summary);
            });

            this.setButtons([
                { text: strings.cancel, click: function() { $(this).dialog('close'); } },
                { text: strings.start, 'class': 'button-primary apexfo-start', disabled: true, click: function() { self.startAnalysis(); } }
            ]);

            this.loadEstimate($summary);
        },

        loadEstimate: function($summary) {
            var self = this;
            $summary.html('<span class="spinner is-active"></span>');
            $('.apexfo-start').prop('disabled', true);

            request('estimate?scope=' + encodeURIComponent(this.scope)).then(function(data) {
                self.estimate = data;
                self.ids = data.ids || [];
                $summary.empty();

                if (!data.configured) {
                    $summary.append(
                        $('<p class="apexfo-estimate-warning"></p>').text(strings.notConfigured),
                        $('<a class="button"></a>').attr('href', data.connectors_url || config.connectorsUrl).text(strings.connectProvider)
                    );
                    return;
                }

                if (!data.files) {
                    $summary.append($('<p></p>').text(strings.nothingToDo));
                    return;
                }

                $summary.append(
                    $('<p class="apexfo-estimate-files"></p>').text(format(strings.filesSummary, formatNumber(data.files), formatNumber(data.images))),
                    $('<p class="apexfo-estimate-tokens"></p>').text(format(strings.tokensEstimate, formatNumber(data.tokens_low), formatNumber(data.tokens_high))),
                    $('<p class="apexfo-estimate-cost"></p>').text(format(strings.costEstimate, formatMoney(data.cost_low), formatMoney(data.cost_high)))
                );

                $('.apexfo-start').prop('disabled', false);
            }).catch(function(error) {
                $summary.empty().append($('<p class="apexfo-estimate-warning"></p>').text(errorMessage(error)));
            });
        },

        // ------------------------------------------------------------ step 2

        startAnalysis: function() {
            var self = this;
            var $body = this.$dialog.empty();

            this.cancelled = false;
            this.stopRequested = false;
            this.proposals = [];
            this.usage = { input: 0, output: 0, total: 0 };
            this.model = '';
            this.newFolders = [];

            var $progress = $('<div class="apexfo-progress"><div class="apexfo-progress-bar"><span></span></div><p class="apexfo-progress-text"></p><p class="apexfo-progress-usage"></p><p class="apexfo-progress-cost"></p></div>');
            $body.append($progress);

            this.setButtons([
                { text: strings.cancel, click: function() { self.cancelled = true; $(this).dialog('close'); } },
                { text: strings.stopAndReview, 'class': 'apexfo-stop', disabled: true, click: function() { self.stopRequested = true; $(this).prop('disabled', true); } }
            ]);

            var batches = [];
            for (var i = 0; i < this.ids.length; i += batchSize) {
                batches.push(this.ids.slice(i, i + batchSize));
            }

            var done = 0;
            var update = function() {
                var percent = self.ids.length ? Math.round(done / self.ids.length * 100) : 100;
                $progress.find('.apexfo-progress-bar span').css('width', percent + '%');
                $progress.find('.apexfo-progress-text').text(format(strings.progress, formatNumber(done), formatNumber(self.ids.length)));

                if (self.usage.total) {
                    $progress.find('.apexfo-progress-usage').text(format(strings.usage, formatNumber(self.usage.total), self.model || ''));
                    var cost = self.costSoFar();
                    var projected = done ? cost / done * self.ids.length : cost;
                    $progress.find('.apexfo-progress-cost').text(format(strings.costSoFar, formatMoney(cost), formatMoney(projected)));
                }
            };
            update();

            var run = function(index) {
                if (self.cancelled || !self.$dialog) {
                    return;
                }
                // Everything analysed, or the user asked to stop and review what is done so far
                if (index >= batches.length || self.stopRequested) {
                    self.showReview();
                    return;
                }

                request('plan', {
                    method: 'POST',
                    data: {
                        ids: batches[index],
                        instructions: self.instructions,
                        known_new_folders: self.newFolders
                    }
                }).then(function(data) {
                    $.each(data.proposals || [], function(i, proposal) {
                        self.proposals.push(proposal);
                        if (proposal.new_folder && $.inArray(proposal.new_folder, self.newFolders) === -1) {
                            self.newFolders.push(proposal.new_folder);
                        }
                    });

                    if (data.usage) {
                        self.usage.input += data.usage.input || 0;
                        self.usage.output += data.usage.output || 0;
                        self.usage.total += data.usage.total || 0;
                    }
                    if (data.model) {
                        self.model = data.model;
                    }

                    done += batches[index].length;
                    update();
                    $('.apexfo-stop').prop('disabled', false);
                    run(index + 1);
                }).catch(function(error) {
                    $progress.append($('<p class="apexfo-estimate-warning"></p>').text(errorMessage(error)));
                    self.setButtons([
                        { text: strings.close, click: function() { $(this).dialog('close'); } }
                    ]);
                });
            };

            run(0);
        },

        /**
         * Cost of the tokens used so far, at the mid-point of the estimate's rate range
         */
        costSoFar: function() {
            var rates = (this.estimate && this.estimate.rates) || {};
            var inputRate = ((rates.input_low || 0) + (rates.input_high || 0)) / 2;
            var outputRate = ((rates.output_low || 0) + (rates.output_high || 0)) / 2;
            return (this.usage.input * inputRate + this.usage.output * outputRate) / 1000000;
        },

        // ------------------------------------------------------------ step 3

        /**
         * Proposals grouped by target: existing folders by id, new folders by name
         */
        groupProposals: function() {
            var groups = {};
            var order = [];
            var unplaced = [];
            var folderNames = {};

            $('.apex-folder-list li[data-folder-id]').each(function() {
                folderNames[$(this).data('folder-id')] = $(this).find('.apexfo-name').first().text();
            });

            $.each(this.proposals, function(i, proposal) {
                var key;
                if (proposal.folder_id) {
                    key = 'id:' + proposal.folder_id;
                } else if (proposal.new_folder) {
                    key = 'new:' + proposal.new_folder.toLowerCase();
                } else {
                    unplaced.push(proposal);
                    return;
                }

                if (!groups[key]) {
                    groups[key] = {
                        key: key,
                        folderId: proposal.folder_id || null,
                        name: proposal.folder_id ? (folderNames[proposal.folder_id] || ('#' + proposal.folder_id)) : proposal.new_folder,
                        isNew: !proposal.folder_id,
                        parentId: proposal.parent_id || null,
                        parentName: proposal.parent_id ? folderNames[proposal.parent_id] : null,
                        files: []
                    };
                    order.push(key);
                }
                groups[key].files.push(proposal);
            });

            return {
                groups: $.map(order, function(key) { return groups[key]; }),
                unplaced: unplaced
            };
        },

        showReview: function() {
            var self = this;
            var $body = this.$dialog.empty();
            var grouped = this.groupProposals();
            var placed = this.proposals.length - grouped.unplaced.length;
            var newCount = $.grep(grouped.groups, function(group) { return group.isNew; }).length;

            this.skipped = {};

            $body.append($('<h3 class="apexfo-review-title"></h3>').text(strings.reviewTitle));
            $body.append($('<p class="apexfo-review-summary"></p>').text(format(strings.reviewSummary, formatNumber(placed), formatNumber(grouped.groups.length), formatNumber(newCount))));

            if (!grouped.groups.length) {
                $body.append($('<p class="apexfo-estimate-warning"></p>').text(strings.noProposals));
            }

            var $list = $('<ul class="apexfo-review-list"></ul>');
            $.each(grouped.groups, function(i, group) {
                var $item = $('<li class="apexfo-review-group"></li>');
                var $head = $('<div class="apexfo-review-head"></div>');

                $head.append($('<span class="apexfo-review-name"></span>').text(group.name));
                if (group.isNew) {
                    $head.append($('<span class="apexfo-review-badge"></span>').text(strings.newFolder));
                    if (group.parentName) {
                        $head.append($('<span class="apexfo-review-parent"></span>').text(strings.subfolderOf + ' ' + group.parentName));
                    }
                }
                $head.append($('<span class="apexfo-count"></span>').text(group.files.length));

                var $skip = $('<label class="apexfo-review-skip"></label>').append(
                    $('<input type="checkbox">').on('change', function() {
                        self.skipped[group.key] = this.checked;
                        $item.toggleClass('apexfo-skipped', this.checked);
                    }),
                    document.createTextNode(' ' + strings.skipGroup)
                );
                $head.append($skip);

                var $thumbs = $('<div class="apexfo-review-thumbs"></div>');
                $.each(group.files.slice(0, 12), function(j, proposal) {
                    var model = wp.media && wp.media.model ? wp.media.model.Attachment.get(proposal.id) : null;
                    var sizes = model && model.get('sizes');
                    var src = sizes && sizes.thumbnail ? sizes.thumbnail.url : (model && model.get('icon'));
                    var $thumb = $('<span class="apexfo-review-thumb"></span>').attr('title', proposal.reason || '');
                    if (src) {
                        $thumb.append($('<img alt="">').attr('src', src));
                    } else {
                        $thumb.text('#' + proposal.id);
                    }
                    $thumbs.append($thumb);
                });
                if (group.files.length > 12) {
                    $thumbs.append($('<span class="apexfo-review-more"></span>').text('+' + (group.files.length - 12)));
                }

                $item.append($head, $thumbs);
                $list.append($item);
            });
            $body.append($list);

            if (grouped.unplaced.length) {
                $body.append($('<p class="apexfo-review-unplaced"></p>').text(strings.staysUnassigned + ' (' + formatNumber(grouped.unplaced.length) + ')'));
            }

            var $adjust = $('<div class="apexfo-adjust"></div>').append(
                $('<label for="apexfo-adjust-input"></label>').text(strings.adjustLabel),
                $('<textarea id="apexfo-adjust-input" rows="2"></textarea>').attr('placeholder', strings.adjustPlaceholder).val(this.instructions)
            );
            $body.append($adjust);

            if (this.usage.total) {
                $body.append($('<p class="apexfo-review-usage"></p>').text(format(strings.usage, formatNumber(this.usage.total), this.model || '') + ' · ' + formatMoney(this.costSoFar())));
            }

            this.setButtons([
                { text: strings.cancel, click: function() { $(this).dialog('close'); } },
                { text: strings.replan, click: function() { self.instructions = $('#apexfo-adjust-input').val(); self.startAnalysis(); } },
                { text: strings.apply, 'class': 'button-primary', disabled: !grouped.groups.length, click: function() { self.apply(grouped); } }
            ]);
        },

        // ------------------------------------------------------------ step 4

        apply: function(grouped) {
            var self = this;
            var newFolders = [];
            var moves = [];
            var movedTargets = {};

            $.each(grouped.groups, function(i, group) {
                if (self.skipped[group.key]) {
                    return;
                }
                var folderKey = null;
                if (group.isNew) {
                    folderKey = 'new' + i;
                    newFolders.push({ key: folderKey, name: group.name, parent_id: group.parentId || 0 });
                }
                $.each(group.files, function(j, proposal) {
                    var move = { id: proposal.id };
                    if (group.isNew) {
                        move.key = folderKey;
                    } else {
                        move.folder_id = group.folderId;
                        movedTargets[proposal.id] = group.folderId;
                    }
                    moves.push(move);
                });
            });

            this.$dialog.find('.ui-dialog-buttonpane button').prop('disabled', true);
            this.$dialog.append($('<p class="apexfo-applying"></p>').text(strings.applying));

            request('apply', { method: 'POST', data: { new_folders: newFolders, moves: moves } }).then(function(data) {
                // Files moved into existing folders update the grid right away, like drag and drop
                if (wp.media && wp.media.model) {
                    $.each(movedTargets, function(id, folderId) {
                        var model = wp.media.model.Attachment.get(id);
                        if (model.has('apexFolder')) {
                            model.set('apexFolder', parseInt(folderId, 10));
                        }
                    });
                }
                if (typeof window.updateFolderCounts === 'function') {
                    window.updateFolderCounts();
                }
                self.showDone(data);
            }).catch(function(error) {
                self.$dialog.find('.apexfo-applying').remove();
                self.$dialog.append($('<p class="apexfo-estimate-warning"></p>').text(errorMessage(error)));
                self.$dialog.find('.ui-dialog-buttonpane button').prop('disabled', false);
            });
        },

        showDone: function(data) {
            var self = this;
            var $body = this.$dialog.empty();

            $body.append($('<h3 class="apexfo-review-title"></h3>').text(strings.doneTitle));
            $body.append($('<p></p>').text(format(strings.doneSummary, formatNumber(data.moved), formatNumber(data.created))));

            $.each(data.failures || [], function(i, failure) {
                $body.append($('<p class="apexfo-estimate-warning"></p>').text(failure));
            });

            var buttons = [];
            if (data.created) {
                buttons.push({ text: strings.reload, 'class': 'button-primary', click: function() { window.location.reload(); } });
            }
            if (data.can_undo) {
                buttons.push({ text: strings.undo, click: function() { self.undo(); } });
            }
            buttons.push({ text: strings.close, click: function() { $(this).dialog('close'); } });
            this.setButtons(buttons);
        },

        undo: function() {
            var self = this;
            this.$dialog.find('.ui-dialog-buttonpane button').prop('disabled', true);

            request('undo', { method: 'POST' }).then(function() {
                self.$dialog.empty().append($('<p></p>').text(strings.undone));
                self.setButtons([
                    { text: strings.reload, 'class': 'button-primary', click: function() { window.location.reload(); } }
                ]);
            }).catch(function(error) {
                self.$dialog.append($('<p class="apexfo-estimate-warning"></p>').text(errorMessage(error)));
                self.$dialog.find('.ui-dialog-buttonpane button').prop('disabled', false);
            });
        }
    };

    $(document).on('click', '.apexfo-organize', function(e) {
        e.preventDefault();
        Organizer.open();
    });

})(jQuery);
