#!/bin/sh

check_if_git_repo () {
   git rev-parse --git-dir > /dev/null 2>&1
}

xtract_destination_dir () {
   p_line="$1"
   echo $p_line | sed -r 's/^.*[[:blank:]]+([^[:blank:]]*)$/\1/'
}

is_untracked () {
   p_source_file="$1"

   [ -n "$(git ls-files -o --directory --exclude-standard $p_source_file)" ]
}

subst_source_2_destination () {
   p_source_file="$1"
   p_destination_dir="$2"
   p_package="$3"

   echo debian/$p_package/$p_destination_dir/$(basename "$p_source_file")
}

untracked_destination_file () {
   p_source_file="$1"
   p_destination_dir="$2"
   p_package="$3"

   if is_untracked "$p_source_file"; then
      subst_source_2_destination	\
	 "$p_source_file" "$p_destination_dir" "$p_package"
   fi
}

### MAIN ###

p_package="$1"
g_script="$(basename $0)"

check_if_git_repo || exit 0
v_package_install_file="debian/$p_package.install"
#echo "DEBUG: package install file: $v_package_install_file" >&2
cat $v_package_install_file |
while read v_line; do
#echo "DEBUG: line: $v_line" >&2
# It looks like dh_install does no better parsing of the .install file than as
# simple AWK-like split so lets not worry too much here
   v_source_files="$(echo $v_line | sed 's/[^[:blank:]]*$//')"
      # Note that we did the glob resolution here
#echo "DEBUG: source files: $v_source_files" >&2
   if [ -z "$v_source_files" ]; then
      echo "ERROR: $g_script is not compatible with $v_package_install_file" >&2
      exit 1
   fi
   v_destination_dir=$(xtract_destination_dir "$v_line")
#echo "DEBUG: destination dir: $v_destination_dir" >&2
   for v_source_file in $v_source_files; do
      untracked_destination_file	\
	    "$v_source_file" "$v_destination_dir" "$p_package"	|
      xargs -r rm -r 
   done
done
