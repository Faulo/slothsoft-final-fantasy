<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns="http://www.w3.org/1999/xhtml" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sf1="http://schema.slothsoft.net/final-fantasy/i" exclude-result-prefixes="sf1">
    <!-- D4-E1, retaining the unused E0 tile in the private-use area. -->
    <xsl:variable name="item-glyphs" select="'&#x1F5E1;&#x1F528;&#x1F52A;&#x1FA93;&#x1FA84;&#x1F962;&#x1F9BA;&#x26E8;&#x1FA96;&#x1F9E4;&#x1F48D;&#x1F455;&#xE0E0;&#x1F9F4;'" />
    <xsl:key name="classes" match="sf1:classes/sf1:class" use="@id" />
    <xsl:key name="spells" match="sf1:spells/sf1:spell" use="@id" />
    <xsl:template match="/*">
        <article class="final-fantasy">
            <header>
                <p class="eyebrow">Nintendo Entertainment System · US edition</p>
                <h1>Final Fantasy</h1>
                <nav aria-label="Final Fantasy">
                    <a href="/FinalFantasy/I/">Overview</a>
                    <a href="/FinalFantasy/I/SaveEditor/">Equipment editor</a>
                    <a href="/FinalFantasy/I/Weapons/">Weapons</a>
                    <a href="/FinalFantasy/I/Armor/">Armor</a>
                    <a href="/FinalFantasy/I/Spells/">Spells</a>
                </nav>
            </header>
            <xsl:apply-templates select=".//sf1:final-fantasy" />
        </article>
    </xsl:template>
    <xsl:template match="sf1:final-fantasy">
        <xsl:choose>
            <xsl:when test="@view = 'home'">
                <h2>The equipment and magic of Final Fantasy</h2>
                <p>Explore the original US game's weapons, armor, and spells, with names and properties read directly from the game ROM.</p>
                <div class="ff-cards">
                    <a href="/FinalFantasy/I/SaveEditor/">
                        <h3>Equipment editor</h3>
                        <p>Load your NES battery save, change your party's equipment, and download the edited save.</p>
                    </a>
                    <a href="/FinalFantasy/I/Weapons/">
                        <h3>40 weapons</h3>
                        <p>Damage, hit bonuses, critical rates, usable spells, and class permissions.</p>
                    </a>
                    <a href="/FinalFantasy/I/Armor/">
                        <h3>40 pieces of armor</h3>
                        <p>Protection, evasion penalties, resistances, usable spells, and class permissions.</p>
                    </a>
                    <a href="/FinalFantasy/I/Spells/">
                        <h3>64 spells</h3>
                        <p>White and black magic by level, price, and the classes that can learn each spell.</p>
                    </a>
                </div>
            </xsl:when>
            <xsl:when test="sf1:unavailable">
                <p role="status">
                    <xsl:value-of select="sf1:unavailable" />
                </p>
            </xsl:when>
            <xsl:when test="@view = 'editor'">
                <xsl:call-template name="editor" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:choose>
                    <xsl:when test="@view = 'weapons'">
                        <h2>Weapons</h2>
                        <p>Critical values are the thresholds used by the original US game against a roll from 0 to 200, capped by the attack's hit chance. Weapon element and enemy-type bonuses have no effect in this version.</p>
                        <div class="ff-table">
                            <table>
                                <caption>Weapon properties and equip permissions</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Weapon</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Damage</th>
                                        <th scope="col">Hit bonus</th>
                                        <th scope="col">Critical</th>
                                        <th scope="col">Casts</th>
                                        <th scope="col">Classes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <xsl:for-each select="sf1:weapons/sf1:weapon">
                                        <tr id="weapon-{@id}">
                                            <th scope="row" class="ff-text">
                                                <xsl:call-template name="item-name" />
                                            </th>
                                            <td>
                                                <xsl:value-of select="@price" />
                                            </td>
                                            <td>
                                                <xsl:value-of select="@damage" />
                                            </td>
                                            <td>
                                                <xsl:value-of select="@hit" />
                                            </td>
                                            <td>
                                                <xsl:value-of select="@critical" />
                                            </td>
                                            <td>
                                                <xsl:call-template name="cast" />
                                            </td>
                                            <td>
                                                <xsl:call-template name="classes" />
                                            </td>
                                        </tr>
                                    </xsl:for-each>
                                </tbody>
                            </table>
                        </div>
                    </xsl:when>
                    <xsl:when test="@view = 'armor'">
                        <h2>Armor</h2>
                        <div class="ff-table">
                            <table>
                                <caption>Armor properties and equip permissions</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Armor</th>
                                        <th scope="col">Category</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Absorb</th>
                                        <th scope="col">Evade penalty</th>
                                        <th scope="col">Resists</th>
                                        <th scope="col">Casts</th>
                                        <th scope="col">Classes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <xsl:for-each select="sf1:armor/sf1:item">
                                        <tr id="armor-{@id}">
                                            <th scope="row" class="ff-text">
                                                <xsl:call-template name="item-name" />
                                            </th>
                                            <td>
                                                <xsl:choose>
                                                    <xsl:when test="@category=0">Body</xsl:when>
                                                    <xsl:when test="@category=1">Shield</xsl:when>
                                                    <xsl:when test="@category=2">Helmet</xsl:when>
                                                    <xsl:otherwise>Gloves</xsl:otherwise>
                                                </xsl:choose>
                                            </td>
                                            <td>
                                                <xsl:value-of select="@price" />
                                            </td>
                                            <td>
                                                <xsl:value-of select="@absorb" />
                                            </td>
                                            <td>
                                                <xsl:value-of select="@evade-penalty" />
                                            </td>
                                            <td>
                                                <xsl:call-template name="resistance" />
                                            </td>
                                            <td>
                                                <xsl:call-template name="cast" />
                                            </td>
                                            <td>
                                                <xsl:call-template name="classes" />
                                            </td>
                                        </tr>
                                    </xsl:for-each>
                                </tbody>
                            </table>
                        </div>
                    </xsl:when>
                    <xsl:when test="@view = 'spells'">
                        <h2>Spells</h2>
                        <p>Class permissions describe which spells can be learned. Equipment can cast its listed spell independently of these learning permissions.</p>
                        <div class="ff-table">
                            <table>
                                <caption>Spell levels, prices, and learning permissions</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Spell</th>
                                        <th scope="col">Level</th>
                                        <th scope="col">School</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Classes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <xsl:for-each select="sf1:spells/sf1:spell">
                                        <tr id="spell-{@id}">
                                            <th scope="row" class="ff-text">
                                                <xsl:value-of select="@name" />
                                            </th>
                                            <td>
                                                <xsl:value-of select="@level" />
                                            </td>
                                            <td>
                                                <xsl:value-of select="@school" />
                                            </td>
                                            <td>
                                                <xsl:value-of select="@price" />
                                            </td>
                                            <td>
                                                <xsl:call-template name="classes" />
                                            </td>
                                        </tr>
                                    </xsl:for-each>
                                </tbody>
                            </table>
                        </div>
                    </xsl:when>
                </xsl:choose>
                <p class="ff-note">Names and their typeface come from the ROM. Prices are in gold.</p>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <xsl:template name="item-name">
        <xsl:choose>
            <xsl:when test="sf1:label">
                <xsl:apply-templates select="sf1:label/node()" mode="item-name" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="@name" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <xsl:template match="sf1:icon" mode="item-name">
        <xsl:call-template name="item-icon">
            <xsl:with-param name="glyph" select="@glyph" />
            <xsl:with-param name="label" select="@label" />
        </xsl:call-template>
    </xsl:template>
    <xsl:template match="sf1:icon" mode="item-text">
        <xsl:value-of select="substring($item-glyphs, @glyph - 211, 1)" />
    </xsl:template>
    <xsl:template name="item-icon">
        <xsl:param name="glyph" />
        <xsl:param name="label" />
        <xsl:param name="class" select="'ff-item-icon'" />
        <span class="{$class}" data-glyph="{$glyph}" role="img" aria-label="{$label}" title="{$label}">
            <xsl:choose>
                <xsl:when test="string($glyph) != ''">
                    <xsl:attribute name="style">
                        <xsl:value-of select="concat('background-position: ', -16 * ($glyph - 212), 'px 0')" />
                    </xsl:attribute>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:attribute name="hidden">hidden</xsl:attribute>
                </xsl:otherwise>
            </xsl:choose>
            <xsl:if test="$class = 'ff-item-icon'">
                <xsl:value-of select="substring($item-glyphs, $glyph - 211, 1)" />
            </xsl:if>
        </span>
    </xsl:template>
    <xsl:template name="classes">
        <xsl:variable name="allowed" select="sf1:class-ref/@id" />
        <xsl:variable name="verb">
            <xsl:choose>
                <xsl:when test="self::sf1:spell">learn</xsl:when>
                <xsl:otherwise>equip</xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <span class="ff-classes">
            <xsl:for-each select="ancestor::sf1:final-fantasy/sf1:classes/sf1:class">
                <xsl:variable name="label">
                    <xsl:value-of select="@name" />
                    <xsl:choose>
                        <xsl:when test="@id = $allowed">
                            <xsl:text>: can </xsl:text>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:text>: cannot </xsl:text>
                        </xsl:otherwise>
                    </xsl:choose>
                    <xsl:value-of select="$verb" />
                </xsl:variable>
                <span class="ff-class-entry" tabindex="0">
                    <span class="ff-class-sprite" data-class="{@id}" data-allowed="{@id = $allowed}"
                          role="img" aria-label="{$label}">
                        <xsl:attribute name="style">
                            <xsl:text>background-image: url('/slothsoft@final-fantasy/i/gfx/class?class=</xsl:text>
                            <xsl:value-of select="@id" />
                            <xsl:text>')</xsl:text>
                        </xsl:attribute>
                    </span>
                    <span class="ff-class-tooltip" aria-hidden="true">
                        <span class="ff-text">
                            <xsl:value-of select="@name" />
                        </span>
                        <xsl:choose>
                            <xsl:when test="@id = $allowed">
                                <xsl:text>: can </xsl:text>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:text>: cannot </xsl:text>
                            </xsl:otherwise>
                        </xsl:choose>
                        <xsl:value-of select="$verb" />
                    </span>
                </span>
            </xsl:for-each>
        </span>
    </xsl:template>
    <xsl:template name="cast">
        <xsl:choose>
            <xsl:when test="@spell != 0">
                <a href="/FinalFantasy/I/Spells/#spell-{@spell}" class="ff-text">
                    <xsl:value-of select="key('spells', @spell)/@name" />
                </a>
            </xsl:when>
            <xsl:otherwise>—</xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <xsl:template name="resistance">
        <xsl:variable name="mask" select="number(@resistance-mask)" />
        <xsl:choose>
            <xsl:when test="$mask=0">—</xsl:when>
            <xsl:otherwise>
                <ul class="ff-resists">
                    <xsl:if test="$mask mod 2 &gt;=1">
                        <li>Status</li>
                    </xsl:if>
                    <xsl:if test="floor($mask div 2) mod 2 &gt;=1">
                        <li>Poison / stone</li>
                    </xsl:if>
                    <xsl:if test="floor($mask div 4) mod 2 &gt;=1">
                        <li>Time</li>
                    </xsl:if>
                    <xsl:if test="floor($mask div 8) mod 2 &gt;=1">
                        <li>Death</li>
                    </xsl:if>
                    <xsl:if test="floor($mask div 16) mod 2 &gt;=1">
                        <li>Fire</li>
                    </xsl:if>
                    <xsl:if test="floor($mask div 32) mod 2 &gt;=1">
                        <li>Ice</li>
                    </xsl:if>
                    <xsl:if test="floor($mask div 64) mod 2 &gt;=1">
                        <li>Lightning</li>
                    </xsl:if>
                    <xsl:if test="floor($mask div 128) mod 2 &gt;=1">
                        <li>Earth</li>
                    </xsl:if>
                </ul>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <xsl:template name="editor">
        <h2>Equipment editor</h2>
        <p>Edit the four weapon and armor slots of each party member. Check Equipped to use an item; leave it unchecked to carry it.</p>
        <xsl:if test="sf1:error">
            <p class="ff-error" role="alert">
                <xsl:value-of select="sf1:error" />
            </p>
        </xsl:if>
        <form action="/FinalFantasy/I/SaveEditor/" method="post" enctype="multipart/form-data" class="ff-upload">
            <input type="hidden" name="ff-action" value="upload" />
            <input type="hidden" name="MAX_FILE_SIZE" value="8192" />
            <label for="ff-save">NES battery save (.sav, 8 KiB)</label>
            <input id="ff-save" name="ff-save" type="file" accept=".sav,.srm" required="required" />
            <button type="submit">Load save</button>
        </form>
        <xsl:for-each select="sf1:editor">
            <form action="/FinalFantasy/I/SaveEditor/" method="post">
                <input type="hidden" name="ff-action" value="download" />
                <input type="hidden" name="ff-source" value="{@source}" />
                <div class="ff-party">
                    <xsl:for-each select="sf1:character">
                        <fieldset>
                            <legend>
                                <span class="ff-text">
                                    <xsl:value-of select="@name" />
                                </span>
                                <xsl:text> · </xsl:text>
                                <span class="ff-text">
                                    <xsl:value-of select="key('classes', @class)/@name" />
                                </span>
                            </legend>
                            <xsl:for-each select="sf1:weapons | sf1:armor">
                                <h3>
                                    <xsl:choose>
                                        <xsl:when test="self::sf1:weapons">Weapons</xsl:when>
                                        <xsl:otherwise>Armor</xsl:otherwise>
                                    </xsl:choose>
                                </h3>
                                <xsl:apply-templates select="sf1:slot" />
                            </xsl:for-each>
                        </fieldset>
                    </xsl:for-each>
                </div>
                <p>Equip one weapon and at most one piece of each armor category. Class restrictions apply to equipped items.</p>
                <button type="submit">Download edited save</button>
                <p class="ff-note">The download is a new save file. Keep your original as a backup.</p>
            </form>
        </xsl:for-each>
    </xsl:template>
    <xsl:template match="sf1:slot">
        <xsl:variable name="kind" select="local-name(..)" />
        <xsl:variable name="character" select="../../@index" />
        <xsl:variable name="selected" select="@id" />
        <xsl:variable name="selected-icon" select="ancestor::sf1:final-fantasy/*[local-name()=$kind]/*[@id=$selected]/sf1:label/sf1:icon[1]" />
        <xsl:variable name="field" select="concat('equipment[', $character, '][', $kind, '][', @index, ']')" />
        <xsl:variable name="id" select="concat('ff-', $character, '-', $kind, '-', @index)" />
        <div class="ff-slot">
            <label for="{$id}">Slot
                <xsl:value-of select="@index+1" />
            </label>
            <span class="ff-item-picker">
                <select name="{$field}[id]" id="{$id}" class="ff-text">
                    <option value="0">
                        <xsl:if test="$selected=0">
                            <xsl:attribute name="selected">selected</xsl:attribute>
                        </xsl:if>
                        Empty
                    </option>
                    <xsl:for-each select="ancestor::sf1:final-fantasy/*[local-name()=$kind]/*">
                        <option aria-label="{@name}" class="ff-text" value="{@id}" data-icon="{sf1:label/sf1:icon[1]/@glyph}" data-icon-label="{sf1:label/sf1:icon[1]/@label}">
                            <xsl:if test="@id=$selected">
                                <xsl:attribute name="selected">selected</xsl:attribute>
                            </xsl:if>
                            <xsl:variable name="text">
                                <xsl:choose>
                                    <xsl:when test="sf1:label">
                                        <xsl:apply-templates select="sf1:label/node()" mode="item-text" />
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:value-of select="@name" />
                                    </xsl:otherwise>
                                </xsl:choose>
                            </xsl:variable>
                            <xsl:value-of select="normalize-space($text)" />
                        </option>
                    </xsl:for-each>
                </select>
                <xsl:call-template name="item-icon">
                    <xsl:with-param name="glyph" select="$selected-icon/@glyph" />
                    <xsl:with-param name="label" select="$selected-icon/@label" />
                    <xsl:with-param name="class" select="'ff-item-icon ff-selected-icon'" />
                </xsl:call-template>
            </span>
            <label class="ff-equipped">
                <input type="checkbox" name="{$field}[equipped]" value="1">
                    <xsl:if test="@equipped=1">
                        <xsl:attribute name="checked">checked</xsl:attribute>
                    </xsl:if>
                </input>
                Equipped
            </label>
        </div>
    </xsl:template>
</xsl:stylesheet>
