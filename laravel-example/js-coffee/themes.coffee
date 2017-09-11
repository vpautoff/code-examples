$ ->
    if !$('#settings_page').length || !$('.themes').length
        return

    # Change the iframe width.
    $('#width').change ->
        embedCode = $('textarea').text().replace /width:\s\d+/, 'width: ' + $(this).val()
        $('#embed-code').text embedCode

    $('#reset').click ->
        $('#width').val($(this).attr 'data-width').change()

    $('#embed-code').click ->
        @select()
        @focus()

    # Upload custom css file.
    $('#upload_css').click ->
        $('#custom_css_file').click()

    $('#custom_css_file').change ->
        if !@files or !@files[0]
            return
        readFile @files[0]

    if typeof $.fn.dropzone != 'undefined' and $('#themes_form').length > 0
        $('.css-drop-zone').dropzone
            url: document.URL
            paramName: 'custom_css_file',
            acceptedFiles: 'text/css'
            init: () ->
                # Form is not in the dropzone, add form params to request.
                @on 'sending', (file, xhr, formData) ->
                    $.each $('#themes_form').serializeArray(), ->
                        formData.append @name, @value
            addedfile: (file) ->
                $('.status-message').hide()
                readFile file
            success: ->
                showMessageBox $('#themes-message'), 'Settings successfully saved!', 'success'
            error: (file, response) ->
                showMessageBox $('#themes-message'), response, 'error'

    $('#themes_form').submit ->
        if $('.css-drop-zone').get(0).dropzone.files.length > 0
            $('.css-drop-zone').get(0).dropzone.processQueue()
            return false

    readFile = (file) ->
        if file.type != 'text/css'
            showMessageBox $('#themes-message'), 'Invalid file type.', 'error'
            return
        reader = new FileReader
        reader.onload = (evt) ->
            $('.custom-file p').text file.name
        reader.readAsDataURL file
