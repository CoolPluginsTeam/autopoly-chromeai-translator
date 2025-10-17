import { select, dispatch } from "@wordpress/data";

const ClassicSaveSource = (post_data) => {
    const AllowedMetaFields = select('block-atfp/translate').getAllowedMetaFields();

    function splitContentWithDynamicBreaks(content) {
        const result = [];
        const regex = /(\r\n|\r|\n)/g;
      
        let lastIndex = 0;
        let match;
      
        while ((match = regex.exec(content)) !== null) {
          // Push the content before the line break
          if (match.index > lastIndex) {
            result.push(content.slice(lastIndex, match.index));
          }
      
          // Escape line break and wrap with marker
          const escapedBreak = match[0];
      
          result.push(`atfp_skip_${escapedBreak}_atfp`);
      
          lastIndex = regex.lastIndex;
        }
      
        // Push remaining content after the last match
        if (lastIndex < content.length) {
          result.push(content.slice(lastIndex));
        }
      
        return result;
      }

    const fitlerClassicContent = (content) => {
        const arrContent = splitContentWithDynamicBreaks(content);

        arrContent.forEach((text, index) => {
            const entity=(/^&[a-zA-Z0-9#]+;$/.test(text));
            const htmlTag = /^<\/?\s*[a-zA-Z0-9#]+\s*\/?>$/.test(text);
            const isEmptyHtmlTag = /^<\s*\/?\s*[a-zA-Z0-9#]+\s*><\/\s*\/?\s*[a-zA-Z0-9#]+\s*>$/.test(text);
            const blockCommentTag = /<!--[\s\S]*?-->/g.test(text) && text.indexOf('<!--') < text.indexOf('-->');

            const plainText=!entity && !htmlTag && !isEmptyHtmlTag && !blockCommentTag; 

            if(text !== '' && !text.includes('atfp_skip_') && plainText){
                dispatch('block-atfp/translate').contentSaveSource('classic_index_'+index, text);
            }
        });
    }

    Object.keys(post_data).forEach(key => {
        if (key === 'content') {
            fitlerClassicContent(post_data[key]);
        }else if(key === 'metaFields'){
            Object.keys(post_data[key]).forEach(metaKey => {
                // Store meta fields
                if(Object.keys(AllowedMetaFields).includes(metaKey) && AllowedMetaFields[metaKey].inputType === 'string'){
                    if('' !== post_data[key][metaKey][0] && undefined !== post_data[key][metaKey][0]){
                        dispatch('block-atfp/translate').metaFieldsSaveSource(metaKey, post_data[key][metaKey][0]);
                    }
                }
            });

            // Store ACF fields
            if(window.acf){
                acf.getFields().forEach(field => {
                    const fieldData=JSON.parse(JSON.stringify({key: field.data.key, type: field.data.type, name: field.data.name}));
                    let repeaterField = false;
                    // Update repeater fields
                    if(field.$el && field.$el.closest('.acf-field.acf-field-repeater') && field.$el.closest('.acf-field.acf-field-repeater').length > 0){
                        const rowId=field.$el.closest('.acf-row').data('id');
                        const repeaterItemName=field.$el.closest('.acf-field.acf-field-repeater').data('name');
    
                        if(rowId && '' !== rowId){
                            const index=rowId.replace('row-', '');
                        
                            fieldData.name=repeaterItemName+'_'+index+'_'+fieldData.name;
                            repeaterField = true;
                        }
                    }
    
                   if(field.data && field.data.key && Object.keys(AllowedMetaFields).includes(fieldData.name)){
                       const fieldName = field.data.name;
                        const value = field?.val();
    
                       dispatch('block-atfp/translate').metaFieldsSaveSource(fieldName, value);
                   }
                });
            }
        }else if(['title', 'excerpt'].includes(key)){
            if(post_data[key] && post_data[key].trim() !== ''){
                const action = `${key}SaveSource`;
                dispatch('block-atfp/translate')[action](post_data[key]);
            }
        }
    });
};

export default ClassicSaveSource;
