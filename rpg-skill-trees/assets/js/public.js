(function($){
    const __ = function(text){ return text; };
    let state = {
        selectedTrees: [],
        selectedSkills: [],
        remaining: {},
        treeSpent: {},
        conversions: RST_DATA.settings.conversions || [],
        tierPoints: RST_DATA.settings.tier_points || {},
    };

    function resetState(){
        state.selectedTrees = [];
        state.selectedSkills = [];
        state.remaining = $.extend(true, {}, state.tierPoints);
        state.treeSpent = {};
    }

    function renderPoints(){
        let parts = [];
        for (let i=1;i<=4;i++){
            let spent = (state.tierPoints[i] - (state.remaining[i]||0)).toFixed(2);
            parts.push('Tier '+i+': '+spent+'/'+(state.tierPoints[i]||0));
        }
        $('.rst-points-summary').text(parts.join(' | '));
    }

    function conversionRatio(from,to){
        for (let i=0;i<state.conversions.length;i++){
            if (parseInt(state.conversions[i].from)===parseInt(from) && parseInt(state.conversions[i].to)===parseInt(to)){
                return parseFloat(state.conversions[i].ratio);
            }
        }
        return 0;
    }

    function canAfford(tier, cost){
        let result = spendWithConversion($.extend(true, {}, state.remaining), cost, tier);
        return result.success;
    }

    function spendWithConversion(remaining, cost, tier){
        let original = $.extend(true, {}, remaining);
        remaining[tier] = remaining[tier] || 0;
        if (remaining[tier] >= cost){
            remaining[tier] -= cost;
            return {success: true, remaining: remaining};
        }
        let deficit = cost - remaining[tier];
        remaining[tier] = 0;
        for (let i=1;i<=4;i++){
            if (i===tier) continue;
            let available = remaining[i] || 0;
            let ratio = conversionRatio(i, tier);
            if (available <= 0 || ratio <=0) continue;
            let produced = available * ratio;
            if (produced >= deficit){
                let neededFromDonor = deficit / ratio;
                remaining[i] -= neededFromDonor;
                deficit = 0;
                break;
            } else {
                remaining[i] = 0;
                deficit -= produced;
            }
        }
        if (deficit <= 0){
            return {success: true, remaining: remaining};
        }
        return {success: false, remaining: original};
    }

    function toggleSkill($skill){
        const id = parseInt($skill.data('skill-id'));
        const tier = parseInt($skill.data('tier'));
        const cost = parseFloat($skill.data('cost'));
        const treeId = parseInt($skill.data('tree-id'));
        const prereqs = JSON.parse($skill.attr('data-prereqs')) || [];
        const minPrev = parseFloat($skill.data('min-prev')) || 0;

        if (state.selectedSkills.includes(id)){
            // deselect
            state.selectedSkills = state.selectedSkills.filter(s => s !== id);
            state.remaining[tier] = (state.remaining[tier]||0) + cost;
            state.treeSpent[treeId] = state.treeSpent[treeId] || {};
            state.treeSpent[treeId][tier] = Math.max(0, (state.treeSpent[treeId][tier]||0) - cost);
            $skill.removeClass('is-selected');
            renderPoints();
            drawPrereqLines();
            return;
        }

        if (state.selectedTrees.indexOf(treeId) === -1){
            feedback(__('Select the tree before adding its skills.'));
            return;
        }

        for (let p of prereqs){
            if (state.selectedSkills.indexOf(parseInt(p)) === -1){
                feedback(__('Missing prerequisite: ')+p);
                return;
            }
        }

        state.treeSpent[treeId] = state.treeSpent[treeId] || {};
        if (tier > 1){
            let required = getTreeRule(treeId, tier);
            let spentPrev = state.treeSpent[treeId][tier-1] || 0;
            if (spentPrev < required){
                feedback(__('Need at least ')+required+__(' points in previous tier for this tree.'));
                return;
            }
        }

        if (minPrev > 0 && tier > 1){
            let spentPrev = state.treeSpent[treeId][tier-1] || 0;
            if (spentPrev < minPrev){
                feedback(__('Skill requires ')+minPrev+__(' points in previous tier.'));
                return;
            }
        }

        if (!canAfford(tier, cost)){
            feedback(__('Not enough points (including conversions).'));
            return;
        }

        let result = spendWithConversion(state.remaining, cost, tier);
        if (!result.success){
            feedback(__('Not enough points to spend.'));
            return;
        }
        state.remaining = result.remaining;
        state.selectedSkills.push(id);
        state.treeSpent[treeId][tier] = (state.treeSpent[treeId][tier] || 0) + cost;
        $skill.addClass('is-selected');
        renderPoints();
        drawPrereqLines();
    }

    function getTreeRule(treeId, tier){
        let tree = RST_DATA.trees.find(t => parseInt(t.id) === parseInt(treeId));
        if (!tree) return 0;
        return parseFloat(tree.tier_rules[tier] || 0);
    }

    function feedback(text){
        $('.rst-feedback').text(text).addClass('is-visible');
        setTimeout(()=>{$('.rst-feedback').removeClass('is-visible');}, 3000);
    }

    function drawPrereqLines(){
        $('.rst-tree').each(function(){
            const $tree = $(this);
            const $svg = $tree.find('svg.rst-prereq-lines');
            $svg.empty();
            let svgOffset = $svg.offset();
            $tree.find('.rst-skill').each(function(){
                const $skill = $(this);
                const prereqs = JSON.parse($skill.attr('data-prereqs')) || [];
                prereqs.forEach(function(pr){
                    const $prSkill = $tree.find('.rst-skill[data-skill-id="'+pr+'"]');
                    if (!$prSkill.length) return;
                    const from = $prSkill[0].getBoundingClientRect();
                    const to = $skill[0].getBoundingClientRect();
                    const svgRect = $svg[0].getBoundingClientRect();
                    const line = document.createElementNS('http://www.w3.org/2000/svg','line');
                    line.setAttribute('x1', from.left + from.width/2 - svgRect.left);
                    line.setAttribute('y1', from.top + from.height/2 - svgRect.top);
                    line.setAttribute('x2', to.left + to.width/2 - svgRect.left);
                    line.setAttribute('y2', to.top + to.height/2 - svgRect.top);
                    line.setAttribute('stroke', 'var(--rst-tree-color, #888)');
                    line.setAttribute('stroke-width', '2');
                    $svg.append(line);
                });
            });
        });
    }

    function applyTreeVisibility(){
        $('.rst-tree').each(function(){
            const id = parseInt($(this).data('tree-id'));
            if (state.selectedTrees.indexOf(id) === -1){
                $(this).addClass('is-hidden');
            } else {
                $(this).removeClass('is-hidden');
            }
        });
        drawPrereqLines();
    }

    function saveBuild(){
        if (RST_DATA.login_required && !RST_DATA.user_logged_in){
            feedback(__('Login required to save builds.'));
            return;
        }
        $.post(RST_DATA.ajax, {
            action: 'rst_save_build',
            nonce: RST_DATA.nonce,
            skills: state.selectedSkills
        }).done(function(resp){
            if (resp.success){
                feedback(resp.data.message);
            } else {
                feedback(resp.data.message || __('Could not save build.'));
            }
        });
    }

    function loadBuild(){
        $.post(RST_DATA.ajax, {
            action: 'rst_load_build',
            nonce: RST_DATA.nonce
        }).done(function(resp){
            if (resp.success && resp.data.build){
                resetState();
                let skillsFromBuild = resp.data.build.skills.map(s => parseInt(s));
                // ensure trees that contain skills are selected
                skillsFromBuild.forEach(function(id){
                    const $skill = $('.rst-skill[data-skill-id="'+id+'"]');
                    if (!$skill.length) return;
                    const treeId = parseInt($skill.data('tree-id'));
                    if (state.selectedTrees.indexOf(treeId)===-1){
                        state.selectedTrees.push(treeId);
                        $('.rst-tree-toggle[value="'+treeId+'"]').prop('checked', true);
                    }
                    toggleSkill($skill);
                });
            }
            feedback(resp.data.message || __('Build loaded.'));
        });
    }

    function resetBuild(){
        resetState();
        $('.rst-skill').removeClass('is-selected');
        $('.rst-tree-toggle').prop('checked', false);
        applyTreeVisibility();
        renderPoints();
    }

    $(function(){
        resetState();
        renderPoints();

        $('.rst-tree-toggle').on('change', function(){
            const id = parseInt($(this).val());
            if ($(this).is(':checked')){
                if (state.selectedTrees.indexOf(id)===-1){
                    state.selectedTrees.push(id);
                }
            } else {
                state.selectedTrees = state.selectedTrees.filter(t => t !== id);
                // remove skills from deselected tree
                $('.rst-skill[data-tree-id="'+id+'"]').each(function(){
                    if ($(this).hasClass('is-selected')){
                        toggleSkill($(this));
                    }
                });
            }
            applyTreeVisibility();
        });

        $('.rst-skill').on('click keypress', function(e){
            if (e.type === 'keypress' && e.key !== 'Enter') return;
            toggleSkill($(this));
        });

        $('.rst-save-build').on('click', function(){
            saveBuild();
        });
        $('.rst-load-build').on('click', function(){
            loadBuild();
        });
        $('.rst-reset-build').on('click', function(){
            resetBuild();
        });

        $(window).on('resize', function(){
            drawPrereqLines();
        });
        drawPrereqLines();
    });
})(jQuery);
