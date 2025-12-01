jQuery(document).ready(function ($) {
    console.log("✅ Woo Pages Cart JS loaded (Classic Mode)");

    const fieldId = 'villegas_cart_comuna';

    // --- REGION CODE MAP ---
    const regionCodeMap = {
        'arica y parinacota': 'CL-AP',
        'tarapaca': 'CL-TA',
        'antofagasta': 'CL-AN',
        'atacama': 'CL-AT',
        'coquimbo': 'CL-CO',
        'valparaiso': 'CL-VS',
        'metropolitana de santiago': 'CL-RM',
        'region metropolitana': 'CL-RM',
        'región metropolitana': 'CL-RM',
        'libertador general bernardo ohiggins': 'CL-LI',
        'libertador general bernardo o higgins': 'CL-LI',
        'maule': 'CL-ML',
        'nuble': 'CL-NB',
        'ñuble': 'CL-NB',
        'biobio': 'CL-BI',
        'bío-bío': 'CL-BI',
        'araucania': 'CL-AR',
        'la araucania': 'CL-AR',
        'los rios': 'CL-LR',
        'los lagos': 'CL-LL',
        'aysen': 'CL-AI',
        'aysén': 'CL-AI',
        'magallanes': 'CL-MA'
    };

    // --- HELPER FUNCTIONS ---
    function normalizeString(str) {
        return str
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, '')
            .replace(/[^a-zA-ZñÑ0-9\s]/g, '')
            .toLowerCase()
            .replace(/\s+/g, ' ');
    }

    // --- COMUNA DATA PREPARATION ---
    let comunaList = [];
    let comunaToRegionMap = {};

    if (typeof comunasChile !== "undefined") {
        comunasChile.forEach(entry => {
            entry.comunas.forEach(comuna => {
                comunaList.push(comuna);
                const normalized = normalizeString(comuna);
                comunaToRegionMap[normalized] = entry.region;
            });
        });
    } else {
        console.warn("⚠️ comunasChile is not defined. Autocomplete will not work.");
    }

    // --- LEVENSHTEIN DISTANCE FUNCTION ---
    function levenshteinDistance(a, b) {
        const matrix = [];
        for (let i = 0; i <= b.length; i++) matrix[i] = [i];
        for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

        for (let i = 1; i <= b.length; i++) {
            for (let j = 1; j <= a.length; j++) {
                if (b.charAt(i - 1) === a.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }
        return matrix[b.length][a.length];
    }

    // --- FIND CLOSEST MATCH ---
    function findClosestComuna(value) {
        const normalizedValue = normalizeString(value);
        if (!normalizedValue) return null;

        let closest = null;
        let bestScore = 0;

        comunaList.forEach(comuna => {
            const normalizedComuna = normalizeString(comuna);
            const distance = levenshteinDistance(normalizedValue, normalizedComuna);
            const maxLength = Math.max(normalizedValue.length, normalizedComuna.length) || 1;
            const similarity = 1 - (distance / maxLength);

            if (similarity > bestScore) {
                bestScore = similarity;
                closest = comuna;
            }
        });

        // Threshold of 0.6 (60% match)
        return bestScore >= 0.6 ? closest : null;
    }

    // --- AUTOCOMPLETE LOGIC ---
    function initAutocomplete() {
        const $input = $('#' + fieldId);

        if (!$input.length) {
            console.warn("⚠️ Comuna field not found:", fieldId);
            return;
        }

        $input.autocomplete({
            source: function (request, response) {
                const term = request.term;
                const regex = new RegExp("^" + $.ui.autocomplete.escapeRegex(term), "i");
                const matches = comunaList.filter(function (comuna) {
                    return regex.test(comuna);
                });
                response(matches);
            },
            minLength: 1,
            select: function (event, ui) {
                const selectedComuna = ui.item.value;
                console.log("🟢 Comuna selected:", selectedComuna);
                updateShipping(selectedComuna);
            },
            change: function (event, ui) {
                const val = $(this).val();
                const normalized = normalizeString(val);

                // Check if exact match exists in our map
                if (comunaToRegionMap[normalized]) {
                    console.log("✅ Exact match found for:", val);
                    updateShipping(val);
                } else {
                    // Try fuzzy match
                    const closest = findClosestComuna(val);
                    if (closest) {
                        console.log("✨ Fuzzy match found:", closest, "for input:", val);
                        $(this).val(closest); // Auto-correct the input
                        updateShipping(closest);
                    } else {
                        console.warn("❌ No match found for:", val);
                    }
                }
            }
        });
    }

    // --- UPDATE SHIPPING LOGIC ---
    function updateShipping(comuna) {
        const normalized = normalizeString(comuna);
        const regionName = comunaToRegionMap[normalized];

        if (!regionName) {
            console.warn("⚠️ Region not found for comuna:", comuna);
            return;
        }

        const normalizedRegion = normalizeString(regionName);
        const regionCode = regionCodeMap[normalizedRegion];

        console.log("📍 Updating shipping to:", comuna, regionCode);

        // Classic Cart Calculator Fields
        let $calcState = $('#calc_shipping_state');
        let $calcCity = $('#calc_shipping_city');
        let $calcCountry = $('#calc_shipping_country');
        let $calcButton = $('button[name="calc_shipping"]');

        if ($calcState.length) {
            // Ensure country is Chile
            if ($calcCountry.val() !== 'CL') {
                $calcCountry.val('CL').trigger('change');
            }

            // Set State
            $calcState.val(regionCode).trigger('change');

            // Set City
            $calcCity.val(comuna).trigger('change');

            // Trigger Update
            $calcButton.trigger('click');
            console.log("✅ Triggered standard shipping calculator update");
        } else {
            console.warn("⚠️ Standard shipping calculator fields not found. Is the calculator enabled in WooCommerce settings?");
        }
    }

    // --- AUTO-UPDATE CART LOGIC ---
    function initAutoUpdate() {
        // Trigger update when quantity changes
        $(document.body).on('change', '.qty', function () {
            console.log("🔄 Quantity changed, triggering cart update...");
            $('button[name="update_cart"]').trigger('click');
        });

        // Optional: Trigger on Enter key in quantity input
        $(document.body).on('keypress', '.qty', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).trigger('change');
            }
        });
    }

    // Initialize
    initAutocomplete();
    initAutoUpdate();

    // Re-init on cart update (WooCommerce refreshes the cart via AJAX)
    $(document.body).on('updated_cart_totals', function () {
        console.log("🔄 Cart totals updated, re-initializing autocomplete");
        initAutocomplete();
        // initAutoUpdate is delegated to body, so no need to re-init
    });
});
