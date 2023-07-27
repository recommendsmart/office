(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.quote = {
    attach: function (context, settings) {

      let quoteLimit = drupalSettings.quote.quote_limit;
      let quoteCkeditor = drupalSettings.quote.quote_ckeditor_support;
      let quoteHtml = drupalSettings.quote.quote_html_tags_support;

      function getSelectedText() {
        if (document.getSelection) {

          var selectedText = "";

          if (quoteHtml) {
            if (typeof window.getSelection != "undefined") {
              var sel = window.getSelection();
              if (sel.rangeCount) {
                var container = document.createElement("div");
                for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                  container.appendChild(sel.getRangeAt(i).cloneContents());
                }
                selectedText = container.innerHTML;
              }
            } else if (typeof document.selection != "undefined") {
              if (document.selection.type == "Text") {
                selectedText = document.selection.createRange().htmlText;
              }
            }
          } else {
            selectedText = document.getSelection().toString();
          }

        }

        return selectedText.substring(0, quoteLimit);

      }

      function getCommentArea() {
        let commentArea = $(drupalSettings.quote.quote_selector);

        if (quoteCkeditor && $('.cke_wysiwyg_frame').length) {
          commentArea = $('.cke_wysiwyg_frame').contents().find('body');
        }

        return commentArea;
      }

      function getCommentAreaCurValue(commentArea) {
        let curValue = commentArea.val();

        if (quoteCkeditor && $('.cke_wysiwyg_frame').length) {
          curValue = commentArea.html();
        }

        return curValue;
      }

      function setCommentAreaValue(commentArea, value) {
        commentArea.val(value);

        if (quoteCkeditor && $('.cke_wysiwyg_frame').length) {
          commentArea.html(value);
        }
      }

      $(once('comment-quote-sel', '.comment-quote-sel-link')).click(function (e) {
        e.preventDefault();
        let selected = getSelectedText();
        if (selected.length) {
          let commentArea = getCommentArea();
          let curValue = getCommentAreaCurValue(commentArea);
          let parent = $(this).closest('.comment');
          let username = parent.find('.comment__author a').text();
          let value = curValue + '<blockquote><strong>' + Drupal.t('@author wrote:', {'@author': username}) + '</strong> ' + selected + '</blockquote><p><br/></p>';
          setCommentAreaValue(commentArea, value);
          commentArea.focus();
        }

      });

      $(once('comment-quote-all', '.comment-quote-all-link')).click(function (e) {
        e.preventDefault();
        let commentArea = getCommentArea();
        let curValue = getCommentAreaCurValue(commentArea);
        let parent = $(this).closest('.comment');
        let username = parent.find('.comment__author a').text();
        let alltext;

        if (quoteHtml) {
          alltext = parent.find(drupalSettings.quote.quote_selector_comment_quote_all).html().substring(0, quoteLimit);
        } else {
          alltext = parent.find(drupalSettings.quote.quote_selector_comment_quote_all).text().substring(0, quoteLimit);
        }

        let value = curValue + '<blockquote><strong>' + Drupal.t('@author wrote:', {'@author': username}) + '</strong> ' + alltext + '</blockquote><p><br/></p>';
        setCommentAreaValue(commentArea, value);
        commentArea.focus();
      });

      $(once('node-quote-sel', '.node-quote-sel-link')).click(function (e) {
        e.preventDefault();
        let selected = getSelectedText();
        if (selected.length) {
          let commentArea = getCommentArea();
          let curValue = getCommentAreaCurValue(commentArea);
          let parent = $(this).closest('.node');
          let username = parent.find('.node__meta a').first().text();
          let value = curValue + '<blockquote><strong>' + Drupal.t('@author wrote:', {'@author': username}) + '</strong> ' + selected + '</blockquote><p><br/></p>';
          setCommentAreaValue(commentArea, value);
          commentArea.focus();
        }
      });

      $(once('node-quote-all', '.node-quote-all-link')).click(function (e) {
        e.preventDefault();
        let commentArea = getCommentArea();
        let curValue = getCommentAreaCurValue(commentArea);
        let parent = $(this).closest('.node');
        let username = parent.find('.node__meta a').first().text();
        let alltext;

        if (quoteHtml) {
          alltext = parent.find(drupalSettings.quote.quote_selector_node_quote_all).html().substring(0, quoteLimit);
        } else {
          alltext = parent.find(drupalSettings.quote.quote_selector_node_quote_all).text().substring(0, quoteLimit);
        }

        let value = curValue + '<blockquote><strong>' + Drupal.t('@author wrote:', {'@author': username}) + '</strong> ' + alltext + '</blockquote><p><br/></p>';
        setCommentAreaValue(commentArea, value);
        commentArea.focus();
      });

    }
  };

})(jQuery, Drupal, once);
