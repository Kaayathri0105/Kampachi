(function () {
    'use strict';

    function initialize(textarea) {
        const editor = window.wp?.editor;
        if (!textarea.id || textarea.dataset.editorReady || !editor?.getDefaultSettings?.()) {
            return;
        }

        editor.initialize(textarea.id, {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,bullist,numlist,outdent,indent,blockquote,'
                    + 'alignleft,aligncenter,alignright,link,unlink,undo,redo',
                toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap'
            },
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
