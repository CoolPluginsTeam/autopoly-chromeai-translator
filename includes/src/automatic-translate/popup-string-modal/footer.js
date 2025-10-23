import StringPopUpNotice from "./notice";
import { sprintf, __ } from "@wordpress/i18n";
import FormatNumberCount from "../component/format-number-count";

const StringPopUpFooter = (props) => {

    return (
        <div className="modal-footer" key={props.modalRender}>
            {!props.translatePendingStatus && <StringPopUpNotice className="atfp_string_count"><p>{__('Wahooo! You have saved your valuable time via auto translating', 'autopoly-ai-translation-for-polylang')} <strong><FormatNumberCount number={props.characterCount} /></strong> {__('characters using', 'autopoly-ai-translation-for-polylang')} <strong>{props.serviceLabel}</strong>.</p></StringPopUpNotice>}
            <div className="save_btn_cont">
                <button className="notranslate save_it button button-primary" disabled={props.translatePendingStatus} onClick={props.updatePostData}>{props.translateButtonStatus ? <><span className="updating-text">{__("Updating", 'autopoly-ai-translation-for-polylang')}<span className="dot" style={{ "--i": 0 }}></span><span className="dot" style={{ "--i": 1 }}></span><span className="dot" style={{ "--i": 2 }}></span></span></> : __("Update Content", 'autopoly-ai-translation-for-polylang')}</button>
            </div>
        </div>
    );
}

export default StringPopUpFooter;