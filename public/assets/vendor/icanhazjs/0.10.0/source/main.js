/*!
  ICanHaz.js -- by @HenrikJoreteg
*/
/*global  */
(function () {
    function trim(stuff) {
        if (''.trim) return stuff.trim();
        else return s.replace(/^\s+/, '').replace(/\s+$/, '');
    }
    var ich = {
        VERSION: "@VERSION@",
        templates: {},

        // grab jquery or zepto if it's there
        $: (typeof window !== 'undefined') ? window.jQuery || window.Zepto || null : null,

        // public function for adding templates
        // We're enforcing uniqueness to avoid accidental template overwrites.
        // If you want a different template, it should have a different name.
        addTemplate: function (name, templateString) {
                if (ich[name]) throw "Invalid name: " + name + ".";
                if (ich.templates[name]) throw "Template \" + name + \" exists";

            ich.templates[name] = templateString;
            ich[name] = function (data, raw) {
                data = data || {};
                var result = Mustache.to_html(ich.templates[name], data, ich.templates);
                return (ich.$ && !raw) ? ich.$(result) : result;
            };
        },

        // clears all retrieval functions and empties caches
        clearAll: function () {
            for (var key in ich.templates) {
                delete ich[key];
            }
            ich.templates = {};
        },

        // clears/grabs
        refresh: function () {
            ich.clearAll();
            ich.grabTemplates();
        },

        // grabs templates from the DOM and caches them.
        // Loop through and add templates.
        // Whitespace at beginning and end of all templates inside <script> tags will
        // be trimmed. If you want whitespace around a partial, add it in the parent,
        // not the partial. Or do it explicitly using <br/> or &nbsp;
        grabTemplates: function () {
            var i,
                scripts = document.getElementsByTagName('script'),
                l = (scripts !== undefined ? scripts.length : 0),
                script,
                trash = [];
            for (i = 0; i < l; i++) {
                script = scripts[i];
                if (script && script.innerHTML && script.id && (script.type === "text/html" || script.type === "text/x-icanhaz")) {
                    ich.addTemplate(script.id, trim(script.innerHTML));
                    trash.unshift(script);
                }
            }
            for (i = 0, l = trash.length; i < l; i++) {
                trash[i].parentNode.removeChild(trash[i]);
            }
        }
    };

    // attach it to the window
    if (typeof require !== 'undefined') {
        module.exports = ich;
    } else {
        // else make global
        window.ich = ich;
    }

    if (typeof document !== 'undefined') {
        if (ich.$) {
            ich.$(function () {
                ich.grabTemplates();
            });
        } else {
            document.addEventListener('DOMContentLoaded', function () {
                ich.grabTemplates();
            }, false);
        }
    }

})()
