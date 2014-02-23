<h4>{l s='Bongo Sync' mod='dms3bongo'}</h4>
<div class="separation"></div>
<table>
    <tr>
        <td class="col-left">
            <label>{l s='Current status'}:</label>
        </td>
        <td>
            <input type="hidden" name="dms3bongo_oldCurrentStatus" value="{$dms3bongo_currentStatus}"/>
            <select name="dms3bongo_currentStatus">
                <option value="0" {if $dms3bongo_currentStatus == 0}selected="selected"{/if}>{l s='Not sent'}</option>
                <option value="1" {if $dms3bongo_currentStatus == 1}selected="selected"{/if}>{l s='Sent to Bongo but not yet processed'}</option>
                <option value="2" {if $dms3bongo_currentStatus == 2}selected="selected"{/if}>{l s='Processed by Bongo'}</option>
            </select>
            <p class="preference_description">{l s='Set current status to "Not Sent" in order to force the product to be resent.'}</p>
        </td>
    </tr>
    <tr>
        <td class="col-left">
            <label>{l s='Previous status'}:</label>
        </td>
        <td>
            <select name="dms3bongo_previousStatus" disabled>
                <option value="0" {if $dms3bongo_previousStatus == 0}selected="selected"{/if}>{l s='Not sent'}</option>
                <option value="1" {if $dms3bongo_previousStatus == 1}selected="selected"{/if}>{l s='Sent to Bongo but not yet processed'}</option>
                <option value="2" {if $dms3bongo_previousStatus == 2}selected="selected"{/if}>{l s='Processed by Bongo'}</option>
            </select>

        </td>
    </tr>
    <tr>
        <td class="col-left">
            <label>{l s='Last update'}:</label>
        </td>
        <td>
            <input type="text" disabled value="{$dms3bongo_lastUpdated}"/>
            <p class="preference_description">{l s='The last time we get info from Bongo about this product status.'}</p>
        </td>
    </tr>

</table>