#!/bin/sh

check_if_git_repo () {
   git rev-parse --git-dir > /dev/null 2>&1
}

xtract_destination_dir () {
   p_xdd_line="$1"
   echo $p_xdd_line | sed -r 's/^.*[[:blank:]]+([^[:blank:]]*)$/\1/'
}

is_untracked () {
   p_iu_source_file="$1"

   [ -n "$(git ls-files -o --directory --exclude-standard $p_iu_source_file)" ]
}

subst_source_2_destination () {
   p_ss2d_source_file="$1"
   p_ss2d_destination_dir="$2"
   p_ss2d_package="$3"

   v_ss2d_destination="debian/$p_ss2d_package/$p_ss2d_destination_dir"
   v_ss2d_destination=$v_ss2d_destination/$(basename "$p_ss2d_source_file")
   echo $v_ss2d_destination
}

untracked_destination_file () {
   p_udf_source_file="$1"
   p_udf_destination_dir="$2"
   p_udf_package="$3"

   if is_untracked "$p_udf_source_file"; then
      subst_source_2_destination	\
	 "$p_udf_source_file" "$p_udf_destination_dir" "$p_udf_package"
   fi
}

### MAIN ###

p_m_package="$1"
g_script="$(basename $0)"

check_if_git_repo || exit 0
v_m_package_install_file="debian/$p_m_package.install"
echo "DEBUG: package install file: $v_m_package_install_file" >&2
cat $v_m_package_install_file |
while read v_m_line; do
echo "DEBUG: line: $v_m_line" >&2
# It looks like dh_install does no better parsing of the .install file than as
# simple AWK-like split so lets not worry too much here
   v_m_source_files="$(echo $v_m_line | sed 's/[^[:blank:]]*$//')"
      # Note that we did the glob resolution here
echo "DEBUG: source files: $v_source_files" >&2
   if [ -z "$v_m_source_files" ]; then
      echo	\
	 "ERROR: $g_script is not compatible with $v_m_package_install_file" \
      >&2
      exit 1
   fi
   v_m_destination_dir=$(xtract_destination_dir "$v_m_line")
echo "DEBUG: destination dir: $v_m_destination_dir" >&2
   for v_m_source_file in $v_m_source_files; do
      untracked_destination_file	\
	    "$v_m_source_file" "$v_m_destination_dir" "$p_m_package"	|
      xargs -r rm -r 
   done
done
