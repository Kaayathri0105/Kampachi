(function () {
    'use strict';

    function initialize(textarea) {
        const editor = window.wp?.editor;
        const defaults = editor?.getDefaultSettings?.();
        if (!textarea.id || textarea.dataset.editorReady || !defaults) {
            return;
        }

        const richTextEnabled = window.tinymce && Object.keys(defaults.tinymce ?? {}).length > 0;
        editor.initialize(textarea.id, {
            tinymce: richTextEnabled ? {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,bullist,numlist,outdent,indent,blockquote,'
                    + 'alignleft,aligncenter,alignright,link,unlink,undo,redo',
                toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap'
            } : false,
            quicktags: true
        });
        textarea.dataset.editorReady = 'true';
    }

    function save(textarea) {
        const editor = window.tinymce?.get(textarea.id);
        if (editor && !editor.isHidden()) {
            editor.save();
        }
    }

    function remove(textarea) {
        if (!textarea.dataset.editorReady) {
            return;
        }

        save(textarea);
        window.wp.editor.remove(textarea.id);
        delete textarea.dataset.editorReady;
    }

    window.kampachiDescriptionEditor = { initialize, save, remove };
})();
